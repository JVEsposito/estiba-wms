<?php

namespace App\Services\MateriaPrima;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoHidrocoolerMateriaPrima;
use App\Enums\EstadoLoteMateriaPrima;
use App\Enums\EstadoValidacionMp;
use App\Exceptions\ConflictoOperacion;
use App\Models\AsignacionCamaraLoteMateriaPrima;
use App\Models\CalibreValidacion;
use App\Models\Camara;
use App\Models\Cliente;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\EventoLoteMateriaPrima;
use App\Models\LoteMateriaPrima;
use App\Models\ProcesoHidrocoolerMateriaPrima;
use App\Models\RecepcionRomana;
use App\Models\SegmentoValidacionMp;
use App\Models\User;
use App\Models\VariedadValidacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class ServicioLoteMateriaPrima
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos, User $usuario): LoteMateriaPrima
    {
        $hash = $this->hash($this->payloadLote($datos));

        return DB::transaction(function () use ($datos, $usuario, $hash): LoteMateriaPrima {
            $existente = LoteMateriaPrima::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();
            if ($existente) {
                if (! hash_equals($existente->payload_hash, $hash)) {
                    throw new ConflictoOperacion(
                        'El identificador de creación ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargar($existente);
            }

            $preparados = $this->prepararDatos($datos);
            $this->asegurarNumeroUnico(
                $preparados['recepcion']->temporada_id,
                $preparados['recepcion']->cliente_id,
                $datos['numero_lote'],
            );
            $this->asegurarDisponibilidadSegmento(
                $preparados['segmento'],
                $datos,
            );
            $this->asegurarKilosRecepcion(
                $preparados['recepcion'],
                (float) $datos['kilos_netos_confirmados'],
            );

            $lote = LoteMateriaPrima::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                ...$this->atributosLote($datos, $preparados),
                'estado' => EstadoLoteMateriaPrima::Borrador,
                'version' => 1,
                'creado_por_user_id' => $usuario->id,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'lote_creado',
                $usuario,
                $datos['operacion_id'],
                null,
                EstadoLoteMateriaPrima::Borrador,
                ['payload_hash' => $hash],
            );

            return $this->cargar($lote);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(
        LoteMateriaPrima $lote,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        $hash = $this->hash($this->payloadLote($datos));

        return DB::transaction(function () use ($lote, $datos, $usuario, $hash): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()->lockForUpdate()->findOrFail($lote->id);
            $evento = EventoLoteMateriaPrima::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            if ($evento) {
                $this->asegurarEventoIdempotente($evento, $lote, 'lote_actualizado', $hash);

                return $this->cargar($lote);
            }
            if (! $lote->estado->esEditable()) {
                throw new ConflictoOperacion('Solo un lote en borrador puede editarse.');
            }
            if ($lote->version !== (int) $datos['version_conocida']) {
                throw new ConflictoOperacion('El lote cambió desde la última lectura.');
            }

            $preparados = $this->prepararDatos($datos);
            $this->asegurarNumeroUnico(
                $preparados['recepcion']->temporada_id,
                $preparados['recepcion']->cliente_id,
                $datos['numero_lote'],
                $lote->id,
            );
            $this->asegurarDisponibilidadSegmento(
                $preparados['segmento'],
                $datos,
                $lote->id,
            );
            $this->asegurarKilosRecepcion(
                $preparados['recepcion'],
                (float) $datos['kilos_netos_confirmados'],
                $lote->id,
            );

            $lote->update([
                ...$this->atributosLote($datos, $preparados),
                'version' => $lote->version + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'lote_actualizado',
                $usuario,
                $datos['operacion_id'],
                EstadoLoteMateriaPrima::Borrador,
                EstadoLoteMateriaPrima::Borrador,
                ['payload_hash' => $hash, 'version' => $lote->version],
            );

            return $this->cargar($lote);
        }, attempts: 3);
    }

    public function confirmar(
        LoteMateriaPrima $lote,
        string $operacionId,
        int $versionConocida,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use (
            $lote,
            $operacionId,
            $versionConocida,
            $usuario,
        ): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()->lockForUpdate()->findOrFail($lote->id);
            $evento = EventoLoteMateriaPrima::query()
                ->where('operacion_id', $operacionId)
                ->first();
            if ($evento) {
                if ($evento->lote_materia_prima_id !== $lote->id
                    || $evento->tipo !== 'lote_confirmado') {
                    throw new ConflictoOperacion(
                        'El identificador de operación ya fue utilizado con otra acción.',
                    );
                }

                return $this->cargar($lote);
            }
            if ($lote->estado !== EstadoLoteMateriaPrima::Borrador) {
                throw new ConflictoOperacion('El lote ya no se encuentra en borrador.');
            }
            if ($lote->version !== $versionConocida) {
                throw new ConflictoOperacion('El lote cambió desde la última lectura.');
            }

            $segmento = SegmentoValidacionMp::query()
                ->with(['envases', 'validacion'])
                ->lockForUpdate()
                ->findOrFail($lote->segmento_validacion_mp_id);
            $recepcion = RecepcionRomana::query()
                ->lockForUpdate()
                ->findOrFail($segmento->validacion->recepcion_romana_id);
            $this->asegurarDisponibilidadSegmento(
                $segmento,
                [
                    'envase_primario' => $lote->envase_primario->value,
                    'cantidad_envases_primarios' => $lote->cantidad_envases_primarios,
                    'envase_secundario' => $lote->envase_secundario?->value,
                    'cantidad_envases_secundarios' => $lote->cantidad_envases_secundarios,
                ],
                $lote->id,
            );
            $this->asegurarKilosRecepcion(
                $recepcion,
                (float) $lote->kilos_netos_confirmados,
                $lote->id,
            );

            $nuevoEstado = $lote->requiere_hidrocooler
                ? EstadoLoteMateriaPrima::PendienteHidrocooler
                : EstadoLoteMateriaPrima::PendienteAsignacion;
            $lote->update([
                'estado' => $nuevoEstado,
                'version' => $lote->version + 1,
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => now(),
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'lote_confirmado',
                $usuario,
                $operacionId,
                EstadoLoteMateriaPrima::Borrador,
                $nuevoEstado,
                [
                    'requiere_hidrocooler' => $lote->requiere_hidrocooler,
                    'kilos_netos_calculados' => $lote->kilos_netos_calculados,
                    'kilos_netos_confirmados' => $lote->kilos_netos_confirmados,
                ],
            );
            $this->actualizarEstadoSegmento($segmento);

            return $this->cargar($lote);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function iniciarHidrocooler(
        LoteMateriaPrima $lote,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use ($lote, $datos, $usuario): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()->lockForUpdate()->findOrFail($lote->id);
            $existente = ProcesoHidrocoolerMateriaPrima::query()
                ->where('operacion_inicio_id', $datos['operacion_id'])
                ->first();
            if ($existente) {
                if ($existente->lote_materia_prima_id !== $lote->id) {
                    throw new ConflictoOperacion(
                        'El identificador de inicio ya fue utilizado en otro lote.',
                    );
                }

                return $this->cargar($lote);
            }
            if ($lote->estado !== EstadoLoteMateriaPrima::PendienteHidrocooler) {
                throw new ConflictoOperacion('El lote no está pendiente de hidrocooler.');
            }

            $inicio = CarbonImmutable::parse($datos['inicio_at']);
            ProcesoHidrocoolerMateriaPrima::create([
                'lote_materia_prima_id' => $lote->id,
                'operacion_inicio_id' => $datos['operacion_id'],
                'estado' => EstadoHidrocoolerMateriaPrima::EnCurso,
                'equipo' => trim($datos['equipo']),
                'inicio_at' => $inicio,
                'iniciado_por_user_id' => $usuario->id,
            ]);
            $lote->update([
                'estado' => EstadoLoteMateriaPrima::HidrocoolerEnCurso,
                'version' => $lote->version + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'hidrocooler_iniciado',
                $usuario,
                $datos['operacion_id'],
                EstadoLoteMateriaPrima::PendienteHidrocooler,
                EstadoLoteMateriaPrima::HidrocoolerEnCurso,
                ['equipo' => trim($datos['equipo']), 'inicio_at' => $inicio->toAtomString()],
            );

            return $this->cargar($lote);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function completarHidrocooler(
        LoteMateriaPrima $lote,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use ($lote, $datos, $usuario): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()->lockForUpdate()->findOrFail($lote->id);
            $proceso = ProcesoHidrocoolerMateriaPrima::query()
                ->where('lote_materia_prima_id', $lote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $procesoConOperacion = ProcesoHidrocoolerMateriaPrima::query()
                ->where('operacion_termino_id', $datos['operacion_id'])
                ->first();
            if ($procesoConOperacion
                && $procesoConOperacion->lote_materia_prima_id !== $lote->id) {
                throw new ConflictoOperacion(
                    'El identificador de término ya fue utilizado en otro lote.',
                );
            }
            if ($proceso->operacion_termino_id !== null) {
                if ($proceso->operacion_termino_id !== $datos['operacion_id']) {
                    throw new ConflictoOperacion(
                        'El hidrocooler ya fue completado con otra operación.',
                    );
                }

                return $this->cargar($lote);
            }
            if ($lote->estado !== EstadoLoteMateriaPrima::HidrocoolerEnCurso
                || $proceso->estado !== EstadoHidrocoolerMateriaPrima::EnCurso) {
                throw new ConflictoOperacion('El lote no posee un hidrocooler en curso.');
            }

            $termino = CarbonImmutable::parse($datos['termino_at']);
            $inicio = CarbonImmutable::instance($proceso->inicio_at);
            if ($termino->lessThanOrEqualTo($inicio)) {
                throw ValidationException::withMessages([
                    'termino_at' => 'El término debe ser posterior al inicio del hidrocooler.',
                ]);
            }
            $duracion = (int) ceil(($termino->getTimestamp() - $inicio->getTimestamp()) / 60);
            $proceso->update([
                'operacion_termino_id' => $datos['operacion_id'],
                'estado' => EstadoHidrocoolerMateriaPrima::Completado,
                'termino_at' => $termino,
                'duracion_minutos' => $duracion,
                'temperatura_c' => round((float) $datos['temperatura_c'], 2),
                'observacion' => $datos['observacion'] ?? null,
                'completado_por_user_id' => $usuario->id,
            ]);
            $lote->update([
                'estado' => EstadoLoteMateriaPrima::PendienteAsignacion,
                'version' => $lote->version + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'hidrocooler_completado',
                $usuario,
                $datos['operacion_id'],
                EstadoLoteMateriaPrima::HidrocoolerEnCurso,
                EstadoLoteMateriaPrima::PendienteAsignacion,
                [
                    'equipo' => $proceso->equipo,
                    'inicio_at' => $inicio->toAtomString(),
                    'termino_at' => $termino->toAtomString(),
                    'duracion_minutos' => $duracion,
                    'temperatura_c' => (float) $proceso->temperatura_c,
                ],
            );

            return $this->cargar($lote);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function asignarCamara(
        LoteMateriaPrima $lote,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use ($lote, $datos, $usuario): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()->lockForUpdate()->findOrFail($lote->id);
            $existente = AsignacionCamaraLoteMateriaPrima::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            if ($existente) {
                if ($existente->lote_materia_prima_id !== $lote->id) {
                    throw new ConflictoOperacion(
                        'El identificador de asignación ya fue utilizado en otro lote.',
                    );
                }

                return $this->cargar($lote);
            }
            if ($lote->estado !== EstadoLoteMateriaPrima::PendienteAsignacion) {
                throw new ConflictoOperacion('El lote no está pendiente de asignación.');
            }
            $camara = Camara::query()
                ->whereKey($datos['camara_id'])
                ->where('contenido', ContenidoCamara::MateriaPrima->value)
                ->where('estado', EstadoCamara::Activa->value)
                ->lockForUpdate()
                ->first();
            if (! $camara) {
                throw ValidationException::withMessages([
                    'camara_id' => 'Selecciona una cámara activa exclusiva de materia prima.',
                ]);
            }

            AsignacionCamaraLoteMateriaPrima::create([
                'operacion_id' => $datos['operacion_id'],
                'lote_materia_prima_id' => $lote->id,
                'camara_id' => $camara->id,
                'asignado_por_user_id' => $usuario->id,
                'asignado_at' => now(),
                'observacion' => $datos['observacion'] ?? null,
            ]);
            $lote->update([
                'estado' => EstadoLoteMateriaPrima::AsignadoCamara,
                'version' => $lote->version + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $lote,
                'camara_asignada',
                $usuario,
                $datos['operacion_id'],
                EstadoLoteMateriaPrima::PendienteAsignacion,
                EstadoLoteMateriaPrima::AsignadoCamara,
                ['camara_id' => $camara->id, 'camara_codigo' => $camara->codigo],
            );

            return $this->cargar($lote);
        }, attempts: 3);
    }

    public function anular(
        LoteMateriaPrima $lote,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use (
            $lote,
            $operacionId,
            $motivo,
            $usuario,
        ): LoteMateriaPrima {
            $lote = LoteMateriaPrima::query()
                ->with(['hidrocooler', 'asignacionCamara'])
                ->lockForUpdate()
                ->findOrFail($lote->id);
            $evento = EventoLoteMateriaPrima::query()
                ->where('operacion_id', $operacionId)
                ->first();
            if ($evento) {
                if ($evento->lote_materia_prima_id !== $lote->id
                    || $evento->tipo !== 'lote_anulado') {
                    throw new ConflictoOperacion(
                        'El identificador de operación ya fue utilizado con otra acción.',
                    );
                }

                return $this->cargar($lote);
            }
            if ($lote->estado === EstadoLoteMateriaPrima::Anulado) {
                throw new ConflictoOperacion('El lote ya se encuentra anulado.');
            }
            if ($lote->hidrocooler || $lote->asignacionCamara) {
                throw new ConflictoOperacion(
                    'El lote ya posee ejecución física y no puede anularse desde Digitación.',
                );
            }

            $estadoAnterior = $lote->estado;
            $segmento = SegmentoValidacionMp::query()
                ->with(['envases', 'validacion.recepcion'])
                ->lockForUpdate()
                ->findOrFail($lote->segmento_validacion_mp_id);
            $lote->update([
                'estado' => EstadoLoteMateriaPrima::Anulado,
                'clave_numero_vigente' => null,
                'version' => $lote->version + 1,
                'actualizado_por_user_id' => $usuario->id,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);
            $this->registrarEvento(
                $lote,
                'lote_anulado',
                $usuario,
                $operacionId,
                $estadoAnterior,
                EstadoLoteMateriaPrima::Anulado,
                ['motivo' => trim($motivo)],
            );
            $this->actualizarEstadoSegmento($segmento);

            return $this->cargar($lote);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function prepararDatos(array $datos): array
    {
        $segmento = SegmentoValidacionMp::query()
            ->with(['envases', 'validacion'])
            ->lockForUpdate()
            ->findOrFail($datos['segmento_validacion_mp_id']);
        $validacion = $segmento->validacion;
        $recepcion = RecepcionRomana::query()
            ->with('detallesEnvases')
            ->lockForUpdate()
            ->findOrFail($validacion->recepcion_romana_id);
        Cliente::query()->lockForUpdate()->findOrFail($recepcion->cliente_id);
        if ($validacion->estado !== EstadoValidacionMp::Validada) {
            throw new ConflictoOperacion(
                'El segmento todavía no pertenece a una Validación MP confirmada.',
            );
        }
        if (! $recepcion->peso_neto_por_envase
            || ! $recepcion->tipo_envase_calculo_neto
            || ! $recepcion->cantidad_envase_calculo_neto) {
            throw new ConflictoOperacion(
                'Romana debe cerrar la recepción y calcular el peso neto por envase antes de lotizar.',
            );
        }
        if ($datos['envase_primario'] !== $recepcion->tipo_envase_calculo_neto) {
            throw ValidationException::withMessages([
                'envase_primario' => sprintf(
                    'El envase primario debe ser %s, seleccionado por Romana para el cálculo neto.',
                    $recepcion->tipo_envase_calculo_neto,
                ),
            ]);
        }

        $csg = CsgValidacion::query()
            ->whereKey($datos['csg_validacion_id'])
            ->where('temporada_id', $recepcion->temporada_id)
            ->where('activo', true)
            ->first();
        $especie = EspecieValidacion::query()
            ->whereKey($datos['especie_validacion_id'])
            ->where('temporada_id', $recepcion->temporada_id)
            ->where('activo', true)
            ->first();
        $variedad = VariedadValidacion::query()
            ->whereKey($datos['variedad_validacion_id'])
            ->where('especie_validacion_id', $especie?->id)
            ->where('activo', true)
            ->first();
        $calibre = CalibreValidacion::query()
            ->whereKey($datos['calibre_validacion_id'])
            ->where('especie_validacion_id', $especie?->id)
            ->where('activo', true)
            ->first();
        if (! $csg || ! $especie || ! $variedad || ! $calibre) {
            throw ValidationException::withMessages([
                'catalogo' => 'CSG, especie, variedad y calibre deben pertenecer a la temporada y combinación seleccionadas.',
            ]);
        }
        if ($segmento->csg_validacion_id
            && $segmento->csg_validacion_id !== $csg->id) {
            throw ValidationException::withMessages([
                'csg_validacion_id' => 'El CSG debe coincidir con la segregación confirmada en Validación MP.',
            ]);
        }
        if ($segmento->variedad_validacion_id
            && $segmento->variedad_validacion_id !== $variedad->id) {
            throw ValidationException::withMessages([
                'variedad_validacion_id' => 'La variedad debe coincidir con la segregación confirmada en Validación MP.',
            ]);
        }
        if (filled($segmento->cuartel)
            && mb_strtolower(trim($segmento->cuartel)) !== mb_strtolower(trim($datos['cuartel']))) {
            throw ValidationException::withMessages([
                'cuartel' => 'El cuartel debe coincidir con la segregación confirmada en Validación MP.',
            ]);
        }

        return compact('segmento', 'recepcion', 'csg', 'especie', 'variedad', 'calibre');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $preparados
     * @return array<string, mixed>
     */
    private function atributosLote(array $datos, array $preparados): array
    {
        /** @var RecepcionRomana $recepcion */
        $recepcion = $preparados['recepcion'];
        $calculados = round(
            (float) $recepcion->peso_neto_por_envase
            * (int) $datos['cantidad_envases_primarios'],
            3,
        );
        $confirmados = round((float) $datos['kilos_netos_confirmados'], 3);
        $brutos = round((float) $datos['kilos_brutos'], 3);
        if ($confirmados > $brutos) {
            throw ValidationException::withMessages([
                'kilos_netos_confirmados' => 'Los kilos netos no pueden superar los kilos brutos del lote.',
            ]);
        }

        return [
            'segmento_validacion_mp_id' => $preparados['segmento']->id,
            'recepcion_romana_id' => $recepcion->id,
            'temporada_id' => $recepcion->temporada_id,
            'cliente_id' => $recepcion->cliente_id,
            'numero_lote' => $datos['numero_lote'],
            'clave_numero_vigente' => $this->claveNumeroVigente(
                $recepcion->temporada_id,
                $recepcion->cliente_id,
                $datos['numero_lote'],
            ),
            'csg_validacion_id' => $preparados['csg']->id,
            'csg_snapshot' => $preparados['csg']->codigo,
            'sdp' => $datos['sdp'],
            'ggn' => $datos['ggn'],
            'fecha_cosecha' => $datos['fecha_cosecha'],
            'predio' => $datos['predio'],
            'especie_validacion_id' => $preparados['especie']->id,
            'especie_snapshot' => $preparados['especie']->nombre,
            'variedad_validacion_id' => $preparados['variedad']->id,
            'variedad_snapshot' => $preparados['variedad']->nombre,
            'calibre_validacion_id' => $preparados['calibre']->id,
            'calibre_snapshot' => $preparados['calibre']->nombre,
            'cuartel' => $datos['cuartel'],
            'tipo_producto' => $datos['tipo_producto'],
            'envase_primario' => $datos['envase_primario'],
            'envase_secundario' => $datos['envase_secundario'] ?? null,
            'cantidad_envases_primarios' => (int) $datos['cantidad_envases_primarios'],
            'cantidad_envases_secundarios' => (int) ($datos['cantidad_envases_secundarios'] ?? 0),
            'kilos_brutos' => $brutos,
            'kilos_netos_calculados' => $calculados,
            'kilos_netos_confirmados' => $confirmados,
            'requiere_hidrocooler' => (bool) $datos['requiere_hidrocooler'],
            'observacion' => $datos['observacion'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function asegurarDisponibilidadSegmento(
        SegmentoValidacionMp $segmento,
        array $datos,
        ?string $ignorarLoteId = null,
    ): void {
        $disponibles = $segmento->envases
            ->mapWithKeys(fn ($envase): array => [
                $envase->tipo_envase->value => (int) $envase->cantidad,
            ]);
        $reservados = LoteMateriaPrima::query()
            ->where('segmento_validacion_mp_id', $segmento->id)
            ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value)
            ->when($ignorarLoteId, fn ($consulta) => $consulta->where('id', '!=', $ignorarLoteId))
            ->get([
                'envase_primario',
                'envase_secundario',
                'cantidad_envases_primarios',
                'cantidad_envases_secundarios',
            ]);

        $solicitados = [
            $datos['envase_primario'] => (int) $datos['cantidad_envases_primarios'],
        ];
        if (filled($datos['envase_secundario'] ?? null)) {
            $solicitados[$datos['envase_secundario']] =
                ($solicitados[$datos['envase_secundario']] ?? 0)
                + (int) ($datos['cantidad_envases_secundarios'] ?? 0);
        }
        foreach ($solicitados as $tipo => $cantidad) {
            $ocupados = $reservados->sum(function (LoteMateriaPrima $lote) use ($tipo): int {
                $total = $lote->envase_primario->value === $tipo
                    ? $lote->cantidad_envases_primarios
                    : 0;
                if ($lote->envase_secundario?->value === $tipo) {
                    $total += $lote->cantidad_envases_secundarios;
                }

                return $total;
            });
            if (($ocupados + $cantidad) > (int) $disponibles->get($tipo, 0)) {
                throw ValidationException::withMessages([
                    'envases' => sprintf(
                        'La distribución supera los %d %s disponibles en el segmento.',
                        (int) $disponibles->get($tipo, 0),
                        $tipo,
                    ),
                ]);
            }
        }
    }

    private function asegurarKilosRecepcion(
        RecepcionRomana $recepcion,
        float $kilos,
        ?string $ignorarLoteId = null,
    ): void {
        $ocupados = (float) LoteMateriaPrima::query()
            ->where('recepcion_romana_id', $recepcion->id)
            ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value)
            ->when($ignorarLoteId, fn ($consulta) => $consulta->where('id', '!=', $ignorarLoteId))
            ->sum('kilos_netos_confirmados');
        if (($ocupados + $kilos) - (float) $recepcion->peso_neto > 0.001) {
            throw ValidationException::withMessages([
                'kilos_netos_confirmados' => 'La suma de lotes supera el peso neto total de Romana.',
            ]);
        }
    }

    private function asegurarNumeroUnico(
        string $temporadaId,
        string $clienteId,
        string $numero,
        ?string $ignorarLoteId = null,
    ): void {
        $consulta = LoteMateriaPrima::query()
            ->where('temporada_id', $temporadaId)
            ->where('cliente_id', $clienteId)
            ->where('numero_lote', $numero)
            ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value);
        if ($ignorarLoteId) {
            $consulta->where('id', '!=', $ignorarLoteId);
        }
        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'numero_lote' => 'El número de lote ya existe para esta exportadora y temporada.',
            ]);
        }
    }

    private function actualizarEstadoSegmento(SegmentoValidacionMp $segmento): void
    {
        $segmento->loadMissing(['envases', 'validacion.recepcion']);
        $lotesConfirmados = LoteMateriaPrima::query()
            ->where('segmento_validacion_mp_id', $segmento->id)
            ->whereNotIn('estado', [
                EstadoLoteMateriaPrima::Borrador->value,
                EstadoLoteMateriaPrima::Anulado->value,
            ])
            ->get([
                'envase_primario',
                'envase_secundario',
                'cantidad_envases_primarios',
                'cantidad_envases_secundarios',
            ]);
        $cantidadesConfirmadas = [];
        foreach ($lotesConfirmados as $lote) {
            $tipoPrimario = $lote->envase_primario->value;
            $cantidadesConfirmadas[$tipoPrimario] =
                ($cantidadesConfirmadas[$tipoPrimario] ?? 0)
                + $lote->cantidad_envases_primarios;
            if ($lote->envase_secundario) {
                $tipoSecundario = $lote->envase_secundario->value;
                $cantidadesConfirmadas[$tipoSecundario] =
                    ($cantidadesConfirmadas[$tipoSecundario] ?? 0)
                    + $lote->cantidad_envases_secundarios;
            }
        }
        $completo = $segmento->envases->every(
            fn ($envase): bool => ($cantidadesConfirmadas[$envase->tipo_envase->value] ?? 0)
                >= $envase->cantidad,
        );
        $estado = match (true) {
            $lotesConfirmados->isEmpty() => 'pendiente_lote',
            $completo => 'lotizado',
            default => 'lotizacion_parcial',
        };
        $segmento->update(['estado' => $estado]);
    }

    private function claveNumeroVigente(
        string $temporadaId,
        string $clienteId,
        string $numero,
    ): string {
        return hash('sha256', implode('|', [
            $temporadaId,
            $clienteId,
            mb_strtoupper(trim($numero)),
        ]));
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function payloadLote(array $datos): array
    {
        return collect($datos)
            ->except(['version_conocida'])
            ->sortKeys()
            ->all();
    }

    /**
     * @param array<string, mixed>|null $datos
     */
    private function registrarEvento(
        LoteMateriaPrima $lote,
        string $tipo,
        User $usuario,
        ?string $operacionId,
        ?EstadoLoteMateriaPrima $estadoAnterior,
        ?EstadoLoteMateriaPrima $estadoNuevo,
        ?array $datos = null,
    ): void {
        EventoLoteMateriaPrima::create([
            'lote_materia_prima_id' => $lote->id,
            'operacion_id' => $operacionId,
            'tipo' => $tipo,
            'estado_anterior' => $estadoAnterior?->value,
            'estado_nuevo' => $estadoNuevo?->value,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
            'datos' => $datos,
        ]);
    }

    private function asegurarEventoIdempotente(
        EventoLoteMateriaPrima $evento,
        LoteMateriaPrima $lote,
        string $tipo,
        string $hash,
    ): void {
        if ($evento->lote_materia_prima_id !== $lote->id
            || $evento->tipo !== $tipo
            || ! hash_equals((string) data_get($evento->datos, 'payload_hash'), $hash)) {
            throw new ConflictoOperacion(
                'El identificador de operación ya fue utilizado con datos diferentes.',
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        try {
            return hash(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            );
        } catch (JsonException $exception) {
            throw new ConflictoOperacion(
                'No fue posible validar la operación de materia prima.',
                previous: $exception,
            );
        }
    }

    public function cargar(LoteMateriaPrima $lote): LoteMateriaPrima
    {
        return $lote->refresh()->load([
            'segmento.envases',
            'segmento.validacion.recepcion.detallesEnvases',
            'recepcion',
            'temporada',
            'cliente',
            'csg',
            'especie',
            'variedad',
            'calibre',
            'creadoPor',
            'actualizadoPor',
            'confirmadoPor',
            'anuladoPor',
            'hidrocooler.iniciadoPor',
            'hidrocooler.completadoPor',
            'asignacionCamara.camara',
            'asignacionCamara.asignadoPor',
            'eventos.usuario',
        ]);
    }
}
