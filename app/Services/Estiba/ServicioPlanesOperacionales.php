<?php

namespace App\Services\Estiba;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioPlanesOperacionales
{
    /**
     * Punto único de creación para los generadores operacionales futuros.
     *
     * @param  array<int, array{
     *     folio_id: string,
     *     tipo_movimiento: TipoMovimiento,
     *     prioridad?: PrioridadOperacional,
     *     camara_origen_id?: string|null,
     *     posicion_origen_id?: string|null,
     *     camara_destino_id?: string|null,
     *     posicion_destino_id?: string|null,
     *     instruccion?: string|null,
     *     contexto?: array<string, mixed>
     * }>  $tareas
     * @param  array<string, mixed>  $contexto
     */
    public function crear(
        Temporada $temporada,
        TipoPlanOperacional $tipo,
        string $titulo,
        User $creadoPor,
        array $tareas,
        PrioridadOperacional $prioridad = PrioridadOperacional::Normal,
        ?string $motivo = null,
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        array $contexto = [],
    ): PlanOperacional {
        $titulo = trim($titulo);
        $motivo = filled($motivo) ? trim((string) $motivo) : null;
        $referenciaTipo = filled($referenciaTipo) ? trim((string) $referenciaTipo) : null;

        if ($titulo === '' || mb_strlen($titulo) > 180) {
            throw new DomainException('El plan requiere un título de hasta 180 caracteres.');
        }
        if ($tareas === []) {
            throw new DomainException('El plan operacional requiere al menos una tarea.');
        }
        if (($referenciaTipo === null) !== ($referenciaId === null)) {
            throw new DomainException('La referencia del plan debe incluir tipo e identificador.');
        }
        if ($referenciaTipo !== null && mb_strlen($referenciaTipo) > 80) {
            throw new DomainException('El tipo de referencia del plan supera el máximo permitido.');
        }
        if ($referenciaId !== null && ! Str::isUuid($referenciaId)) {
            throw new DomainException('El identificador de referencia del plan no es válido.');
        }

        return DB::transaction(function () use (
            $temporada,
            $tipo,
            $titulo,
            $creadoPor,
            $tareas,
            $prioridad,
            $motivo,
            $referenciaTipo,
            $referenciaId,
            $contexto,
        ): PlanOperacional {
            $temporadaActiva = Temporada::query()
                ->whereKey($temporada->id)
                ->where('activa', true)
                ->lockForUpdate()
                ->first();

            if (! $temporadaActiva) {
                throw new DomainException('Los planes solo pueden crearse en la temporada activa.');
            }
            if (! User::query()->whereKey($creadoPor->id)->where('activo', true)->exists()) {
                throw new DomainException('El usuario que origina el plan no se encuentra activo.');
            }

            $plan = PlanOperacional::create([
                'temporada_id' => $temporadaActiva->id,
                'tipo' => $tipo,
                'estado' => EstadoPlanOperacional::Programado,
                'prioridad' => $prioridad,
                'titulo' => $titulo,
                'motivo' => $motivo,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'contexto' => $contexto !== [] ? $contexto : null,
                'creado_por_user_id' => $creadoPor->id,
                'programado_at' => now(),
            ]);

            foreach (array_values($tareas) as $indice => $datosTarea) {
                $this->crearTarea(
                    $plan,
                    $indice + 1,
                    $datosTarea,
                    $prioridad,
                );
            }

            return $this->cargar($plan);
        }, attempts: 3);
    }

    public function asumir(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): TareaMovimiento {
        return DB::transaction(function () use ($tarea, $usuario, $dispositivo): TareaMovimiento {
            $tareaBloqueada = TareaMovimiento::query()
                ->with('planOperacional.temporada')
                ->lockForUpdate()
                ->findOrFail($tarea->id);
            $plan = $tareaBloqueada->planOperacional;

            $this->validarActor($usuario, $dispositivo);
            $this->validarPlanAsignable($plan);

            if ($tareaBloqueada->estado->esFinal()) {
                throw new DomainException('La tarea ya se encuentra finalizada.');
            }
            if ($tareaBloqueada->responsable_user_id !== null) {
                if ($tareaBloqueada->responsable_user_id === $usuario->id
                    && $tareaBloqueada->dispositivo_id === $dispositivo->id) {
                    return $this->cargarTarea($tareaBloqueada);
                }

                throw new ConflictoOperacion('La tarea ya fue asumida por otro camarero o dispositivo.');
            }
            if ($tareaBloqueada->estado !== EstadoTareaMovimiento::Pendiente) {
                throw new ConflictoOperacion('La tarea cambió de estado antes de ser asumida.');
            }

            $tareaBloqueada->update([
                'estado' => EstadoTareaMovimiento::Asumida,
                'responsable_user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'asumida_at' => now(),
                'version' => $tareaBloqueada->version + 1,
            ]);

            if ($plan->estado === EstadoPlanOperacional::Programado) {
                $plan->update([
                    'estado' => EstadoPlanOperacional::EnEjecucion,
                    'iniciado_por_user_id' => $usuario->id,
                    'iniciado_at' => now(),
                    'version' => $plan->version + 1,
                ]);
            }

            return $this->cargarTarea($tareaBloqueada->refresh());
        }, attempts: 3);
    }

    public function liberar(
        TareaMovimiento $tarea,
        User $usuario,
        Dispositivo $dispositivo,
    ): TareaMovimiento {
        return DB::transaction(function () use ($tarea, $usuario, $dispositivo): TareaMovimiento {
            $tareaBloqueada = TareaMovimiento::query()
                ->with('planOperacional.temporada')
                ->lockForUpdate()
                ->findOrFail($tarea->id);

            $this->validarActor($usuario, $dispositivo);
            $this->validarPlanAsignable($tareaBloqueada->planOperacional);

            if ($tareaBloqueada->estado === EstadoTareaMovimiento::Pendiente
                && $tareaBloqueada->responsable_user_id === null) {
                return $this->cargarTarea($tareaBloqueada);
            }
            if ($tareaBloqueada->estado !== EstadoTareaMovimiento::Asumida) {
                throw new DomainException('Solo una tarea asumida puede volver a la bandeja.');
            }
            if ($tareaBloqueada->responsable_user_id !== $usuario->id
                || $tareaBloqueada->dispositivo_id !== $dispositivo->id) {
                throw new ConflictoOperacion('La tarea pertenece a otro camarero o dispositivo.');
            }

            $tareaBloqueada->update([
                'estado' => EstadoTareaMovimiento::Pendiente,
                'responsable_user_id' => null,
                'dispositivo_id' => null,
                'asumida_at' => null,
                'version' => $tareaBloqueada->version + 1,
            ]);

            return $this->cargarTarea($tareaBloqueada->refresh());
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearTarea(
        PlanOperacional $plan,
        int $secuencia,
        array $datos,
        PrioridadOperacional $prioridadPlan,
    ): TareaMovimiento {
        $tipo = $datos['tipo_movimiento'] ?? null;

        if (! $tipo instanceof TipoMovimiento || $tipo === TipoMovimiento::Reversion) {
            throw new DomainException('La tarea requiere un tipo de movimiento físico válido.');
        }

        $folio = Folio::query()->lockForUpdate()->find($datos['folio_id'] ?? null);
        if (! $folio
            || $folio->temporada_id !== $plan->temporada_id
            || ! $folio->activo
            || $folio->tipo_bulto !== TipoBulto::Pallet) {
            throw new DomainException('Las tareas operacionales solo admiten pallets completos activos de la temporada.');
        }
        $tareaActiva = TareaMovimiento::query()
            ->where('folio_id', $folio->id)
            ->whereIn('estado', [
                EstadoTareaMovimiento::Pendiente->value,
                EstadoTareaMovimiento::Asumida->value,
                EstadoTareaMovimiento::EnProceso->value,
            ])
            ->lockForUpdate()
            ->first(['id']);
        if ($tareaActiva) {
            throw new ConflictoOperacion('El pallet ya posee una tarea operacional activa.');
        }

        $camaraOrigen = $this->camara($datos['camara_origen_id'] ?? null);
        $posicionOrigen = $this->posicion($datos['posicion_origen_id'] ?? null);
        $camaraDestino = $this->camara($datos['camara_destino_id'] ?? null);
        $posicionDestino = $this->posicion($datos['posicion_destino_id'] ?? null);

        $this->validarExtremo($camaraOrigen, $posicionOrigen, 'origen');
        $this->validarExtremo($camaraDestino, $posicionDestino, 'destino');
        $this->validarEstructura($tipo, $camaraOrigen, $posicionOrigen, $camaraDestino, $posicionDestino);

        $prioridad = $datos['prioridad'] ?? $prioridadPlan;
        if (! $prioridad instanceof PrioridadOperacional) {
            throw new DomainException('La prioridad de la tarea no es válida.');
        }
        if (isset($datos['contexto']) && ! is_array($datos['contexto'])) {
            throw new DomainException('El contexto de la tarea debe ser una estructura válida.');
        }

        return TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'secuencia' => $secuencia,
            'tipo_movimiento' => $tipo,
            'estado' => EstadoTareaMovimiento::Pendiente,
            'prioridad' => $prioridad,
            'folio_id' => $folio->id,
            'camara_origen_id' => $camaraOrigen?->id,
            'posicion_origen_id' => $posicionOrigen?->id,
            'camara_destino_id' => $camaraDestino?->id,
            'posicion_destino_id' => $posicionDestino?->id,
            'instruccion' => filled($datos['instruccion'] ?? null)
                ? trim((string) $datos['instruccion'])
                : null,
            'contexto' => ($datos['contexto'] ?? []) !== [] ? $datos['contexto'] : null,
        ]);
    }

    private function validarPlanAsignable(PlanOperacional $plan): void
    {
        if (! $plan->temporada?->activa) {
            throw new DomainException('La tarea no pertenece a la temporada activa.');
        }
        if ($plan->estado === EstadoPlanOperacional::Pausado) {
            throw new DomainException('El plan se encuentra pausado por supervisión.');
        }
        if ($plan->estado->esFinal()) {
            throw new DomainException('El plan ya se encuentra finalizado.');
        }
    }

    private function validarActor(User $usuario, Dispositivo $dispositivo): void
    {
        if (! User::query()->whereKey($usuario->id)->where('activo', true)->exists()
            || ! Dispositivo::query()->whereKey($dispositivo->id)->where('activo', true)->exists()) {
            throw new DomainException('El camarero o la tablet no se encuentra activo.');
        }
    }

    private function camara(?string $id): ?Camara
    {
        return $id ? Camara::query()->findOrFail($id) : null;
    }

    private function posicion(?string $id): ?Posicion
    {
        return $id ? Posicion::query()->findOrFail($id) : null;
    }

    private function validarExtremo(?Camara $camara, ?Posicion $posicion, string $nombre): void
    {
        if (($camara === null) !== ($posicion === null)) {
            throw new DomainException("El extremo de {$nombre} debe incluir cámara y posición.");
        }
        if ($camara && $posicion?->camara_id !== $camara->id) {
            throw new DomainException("La posición de {$nombre} no pertenece a la cámara indicada.");
        }
        if ($camara
            && ($camara->contenido !== ContenidoCamara::Productos
                || $camara->estado !== EstadoCamara::Activa
                || $posicion?->estado !== EstadoPosicion::Activa)) {
            throw new DomainException("El extremo de {$nombre} no está habilitado para pallets de producto terminado.");
        }
    }

    private function validarEstructura(
        TipoMovimiento $tipo,
        ?Camara $camaraOrigen,
        ?Posicion $posicionOrigen,
        ?Camara $camaraDestino,
        ?Posicion $posicionDestino,
    ): void {
        $origen = $camaraOrigen !== null && $posicionOrigen !== null;
        $destino = $camaraDestino !== null && $posicionDestino !== null;
        $valida = match ($tipo) {
            TipoMovimiento::UbicacionInicial => ! $origen && $destino,
            TipoMovimiento::Reubicacion => $origen && $destino
                && $camaraOrigen->id === $camaraDestino->id
                && $posicionOrigen->id !== $posicionDestino->id,
            TipoMovimiento::TrasladoEntreCamaras => $origen && $destino
                && $camaraOrigen->id !== $camaraDestino->id,
            TipoMovimiento::Retiro => $origen && ! $destino,
            TipoMovimiento::Reversion => false,
        };

        if (! $valida) {
            throw new DomainException('El origen y destino no corresponden al tipo de tarea.');
        }
    }

    private function cargar(PlanOperacional $plan): PlanOperacional
    {
        return $plan->load([
            'temporada:id,codigo,nombre,activa',
            'creadoPor:id,name',
            'iniciadoPor:id,name',
            'tareas' => fn ($consulta) => $consulta->with($this->relacionesTarea()),
        ]);
    }

    private function cargarTarea(TareaMovimiento $tarea): TareaMovimiento
    {
        return $tarea->load([
            ...$this->relacionesTarea(),
            'planOperacional:id,temporada_id,tipo,estado,prioridad,titulo,version',
        ]);
    }

    /** @return array<int, string> */
    private function relacionesTarea(): array
    {
        return [
            'folio:id,numero_folio,tipo_bulto',
            'camaraOrigen:id,nombre',
            'posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
            'camaraDestino:id,nombre',
            'posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
            'responsable:id,name',
            'dispositivo:id,codigo,nombre',
        ];
    }
}
