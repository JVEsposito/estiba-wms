<?php

namespace App\Services\Operacion;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoAdministrativoTunelPrefrio;
use App\Enums\EstadoCamara;
use App\Enums\EstadoFolioProcesoPrefrio;
use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\EstadoSesionEstiba;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\EstadoTecnicoTunelPrefrio;
use App\Models\Camara;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\ProcesoPrefrio;
use App\Models\RegistroControlAmbiental;
use App\Models\SesionEstiba;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\TunelPrefrio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class ServicioOperacionAhora
{
    /**
     * @return array<string, mixed>
     */
    public function obtener(Temporada $temporada): array
    {
        $ahora = CarbonImmutable::now();
        $horaOperacional = $ahora->setTimezone(config('app.operational_timezone'));

        return [
            'generado_at' => $ahora->toAtomString(),
            'actualizacion_sugerida_segundos' => 30,
            'jornada' => [
                'fecha' => $horaOperacional->toDateString(),
                'hora' => $horaOperacional->format('H:i:s'),
                'zona_horaria' => $horaOperacional->getTimezone()->getName(),
                'turno' => null,
            ],
            'temporada' => [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
            ],
            'sincronizacion' => $this->sincronizacion($ahora, $horaOperacional),
            'camareros' => $this->camareros($temporada),
            'prefrio' => $this->prefrio($temporada, $ahora),
            'camaras' => $this->camaras($ahora),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function camareros(Temporada $temporada): array
    {
        $sesiones = SesionEstiba::query()
            ->where('estado', EstadoSesionEstiba::Abierta->value)
            ->with([
                'usuario:id,name',
                'dispositivo:id,codigo,nombre',
                'camara:id,codigo,nombre,contenido',
            ])
            ->get()
            ->sortBy(fn (SesionEstiba $sesion): string => mb_strtolower(
                $sesion->usuario->name.'|'.$sesion->camara->codigo.'|'.$sesion->id,
            ))
            ->values();

        if ($sesiones->isEmpty()) {
            return [];
        }

        $tareasPorActor = TareaMovimiento::query()
            ->whereIn('responsable_user_id', $sesiones->pluck('user_id')->unique())
            ->whereIn('dispositivo_id', $sesiones->pluck('dispositivo_id')->unique())
            ->whereIn('estado', [
                EstadoTareaMovimiento::EnProceso->value,
                EstadoTareaMovimiento::Asumida->value,
            ])
            ->whereHas(
                'planOperacional',
                fn (Builder $consulta): Builder => $consulta
                    ->where('temporada_id', $temporada->id),
            )
            ->with([
                'planOperacional:id,temporada_id,tipo,titulo',
                'folio:id,numero_folio,tipo_bulto',
                'camaraOrigen:id,codigo,nombre',
                'posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
                'camaraDestino:id,codigo,nombre',
                'posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
            ])
            ->get()
            ->groupBy(fn (TareaMovimiento $tarea): string => $this->claveActor(
                $tarea->responsable_user_id,
                $tarea->dispositivo_id,
            ))
            ->map(fn (Collection $tareas): ?TareaMovimiento => $tareas
                ->sort(fn (TareaMovimiento $izquierda, TareaMovimiento $derecha): int => $this->ordenTarea($izquierda) <=> $this->ordenTarea($derecha))
                ->first());

        return $sesiones->map(function (SesionEstiba $sesion) use ($tareasPorActor): array {
            /** @var TareaMovimiento|null $tarea */
            $tarea = $tareasPorActor->get($this->claveActor(
                $sesion->user_id,
                $sesion->dispositivo_id,
            ));

            return [
                'usuario' => [
                    'id' => $sesion->usuario->id,
                    'nombre' => $sesion->usuario->name,
                ],
                'dispositivo' => [
                    'id' => $sesion->dispositivo->id,
                    'codigo' => $sesion->dispositivo->codigo,
                    'nombre' => $sesion->dispositivo->nombre,
                ],
                'sesion' => [
                    'id' => $sesion->id,
                    'estado' => $sesion->estado->value,
                    'iniciada_at' => $sesion->iniciada_at?->toAtomString(),
                    'ultima_actividad_at' => $sesion->ultima_actividad_at?->toAtomString(),
                ],
                'ubicacion_actual' => [
                    'tipo' => 'camara',
                    'camara' => [
                        'id' => $sesion->camara->id,
                        'codigo' => $sesion->camara->codigo,
                        'nombre' => $sesion->camara->nombre,
                        'contenido' => $sesion->camara->contenido->value,
                    ],
                ],
                'tarea_actual' => $tarea ? $this->serializarTarea($tarea) : null,
            ];
        })->all();
    }

    private function claveActor(int $usuarioId, string $dispositivoId): string
    {
        return $usuarioId.'|'.$dispositivoId;
    }

    /**
     * @return array{int, int, int, int, string}
     */
    private function ordenTarea(TareaMovimiento $tarea): array
    {
        $fecha = $tarea->iniciada_at ?? $tarea->asumida_at ?? $tarea->created_at;

        return [
            $tarea->estado === EstadoTareaMovimiento::EnProceso ? 0 : 1,
            -$tarea->prioridad->peso(),
            $fecha?->getTimestamp() ?? PHP_INT_MAX,
            $tarea->secuencia,
            $tarea->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarTarea(TareaMovimiento $tarea): array
    {
        return [
            'id' => $tarea->id,
            'estado' => $tarea->estado->value,
            'prioridad' => $tarea->prioridad->value,
            'tipo_movimiento' => $tarea->tipo_movimiento->value,
            'instruccion' => $tarea->instruccion,
            'plan' => [
                'id' => $tarea->planOperacional->id,
                'tipo' => $tarea->planOperacional->tipo->value,
                'titulo' => $tarea->planOperacional->titulo,
            ],
            'folio' => [
                'id' => $tarea->folio->id,
                'numero_folio' => $tarea->folio->numero_folio,
                'tipo_bulto' => $tarea->folio->tipo_bulto->value,
            ],
            'origen' => $this->extremoTarea($tarea->camaraOrigen, $tarea->posicionOrigen),
            'destino' => $this->extremoTarea($tarea->camaraDestino, $tarea->posicionDestino),
            'destino_logico' => $this->destinoLogicoTarea($tarea),
            'asumida_at' => $tarea->asumida_at?->toAtomString(),
            'iniciada_at' => $tarea->iniciada_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extremoTarea(?Camara $camara, ?Posicion $posicion): ?array
    {
        if ($camara === null) {
            return null;
        }

        return [
            'camara' => [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
            ],
            'posicion' => $posicion ? [
                'id' => $posicion->id,
                'etiqueta' => $posicion->etiqueta,
                'banda' => $posicion->banda,
                'posicion' => $posicion->posicion,
                'nivel' => $posicion->nivel,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function destinoLogicoTarea(TareaMovimiento $tarea): ?array
    {
        $contexto = $tarea->contexto ?? [];

        if (($contexto['tipo_decision'] ?? null) !== 'retiro_directo_anden'
            || empty($contexto['anden_id'])) {
            return null;
        }

        return [
            'tipo' => 'anden',
            'id' => $contexto['anden_id'],
            'nombre' => $contexto['anden_nombre'] ?? 'Andén',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prefrio(Temporada $temporada, CarbonImmutable $ahora): array
    {
        $estadosActivos = collect(EstadoProcesoPrefrio::cases())
            ->filter->esActivo()
            ->map->value
            ->all();
        $procesos = ProcesoPrefrio::query()
            ->where('temporada_id', $temporada->id)
            ->whereIn('estado', $estadosActivos)
            ->with([
                'folios' => fn (Builder $consulta): Builder => $consulta
                    ->whereNotIn('estado', [
                        EstadoFolioProcesoPrefrio::Retirado->value,
                        EstadoFolioProcesoPrefrio::Cancelado->value,
                    ]),
            ])
            ->latest('created_at')
            ->get()
            ->unique('tunel_prefrio_id')
            ->keyBy('tunel_prefrio_id');
        $tuneles = TunelPrefrio::query()
            ->withCount([
                'posiciones as posiciones_activas_count' => fn (Builder $consulta): Builder => $consulta
                    ->where('activa', true),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(function (TunelPrefrio $tunel) use ($procesos, $ahora): array {
                /** @var ProcesoPrefrio|null $proceso */
                $proceso = $procesos->get($tunel->id);
                $operable = $tunel->estado_administrativo === EstadoAdministrativoTunelPrefrio::Activo
                    && $tunel->estado_tecnico === EstadoTecnicoTunelPrefrio::Operativo;
                $capacidad = (int) $tunel->posiciones_activas_count;
                $folios = $proceso?->folios ?? collect();
                $ocupadas = min(
                    $capacidad,
                    $folios->pluck('posicion_tunel_prefrio_id')->unique()->count(),
                );
                $admiteCarga = $operable && ($proceso === null || in_array($proceso->estado, [
                    EstadoProcesoPrefrio::Borrador,
                    EstadoProcesoPrefrio::Cargando,
                    EstadoProcesoPrefrio::ListoParaIniciar,
                ], true));

                return [
                    'id' => $tunel->id,
                    'codigo' => $tunel->codigo,
                    'nombre' => $tunel->nombre,
                    'estado_administrativo' => $tunel->estado_administrativo->value,
                    'estado_tecnico' => $tunel->estado_tecnico->value,
                    'estado_operacional' => $this->estadoOperacionalTunel($tunel, $proceso),
                    'operable' => $operable,
                    'capacidad_posiciones' => $capacidad,
                    'posiciones_ocupadas' => $ocupadas,
                    'posiciones_disponibles' => $admiteCarga
                        ? max(0, $capacidad - $ocupadas)
                        : 0,
                    'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $capacidad),
                    'proceso_activo' => $proceso
                        ? $this->serializarProcesoPrefrio($proceso, $ahora, $ocupadas)
                        : null,
                ];
            });
        $tunelesOperables = $tuneles->where('operable', true);
        $capacidad = (int) $tunelesOperables->sum('capacidad_posiciones');
        $ocupadas = (int) $tunelesOperables->sum('posiciones_ocupadas');

        return [
            'resumen' => [
                'tuneles_totales' => $tuneles->count(),
                'tuneles_operables' => $tunelesOperables->count(),
                'tuneles_disponibles' => $tuneles
                    ->where('estado_operacional', 'disponible')
                    ->count(),
                'procesos_activos' => $procesos->count(),
                'procesos_fuera_objetivo' => $tuneles
                    ->filter(fn (array $tunel): bool => (bool) (
                        $tunel['proceso_activo']['objetivo_excedido'] ?? false
                    ))
                    ->count(),
                'folios_en_tunel' => $procesos->sum(fn (ProcesoPrefrio $proceso): int => $proceso->folios->count()),
                'capacidad_operativa' => $capacidad,
                'posiciones_ocupadas' => $ocupadas,
                'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $capacidad),
            ],
            'tuneles' => $tuneles->values()->all(),
        ];
    }

    private function estadoOperacionalTunel(
        TunelPrefrio $tunel,
        ?ProcesoPrefrio $proceso,
    ): string {
        if ($tunel->estado_administrativo !== EstadoAdministrativoTunelPrefrio::Activo) {
            return EstadoAdministrativoTunelPrefrio::Inactivo->value;
        }

        if ($tunel->estado_tecnico !== EstadoTecnicoTunelPrefrio::Operativo) {
            return $tunel->estado_tecnico->value;
        }

        return $proceso?->estado->value ?? 'disponible';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarProcesoPrefrio(
        ProcesoPrefrio $proceso,
        CarbonImmutable $ahora,
        int $posicionesOcupadas,
    ): array {
        $inicio = $proceso->iniciado_at?->toImmutable();
        $corte = $proceso->estado === EstadoProcesoPrefrio::PendienteVerificacion
            ? $proceso->pendiente_verificacion_at?->toImmutable()
            : $ahora;
        $transcurridos = $inicio && $corte
            ? max(0, (int) floor($inicio->diffInMinutes($corte, false)))
            : null;
        $objetivo = $proceso->duracion_objetivo_minutos;
        $objetivoExcedido = $transcurridos !== null
            && $objetivo !== null
            && $transcurridos > $objetivo;

        return [
            'id' => $proceso->id,
            'codigo' => $proceso->codigo,
            'estado' => $proceso->estado->value,
            'setpoint_c' => $proceso->setpoint !== null ? (float) $proceso->setpoint : null,
            'formato_referencia' => $proceso->formato_referencia,
            'folios_cargados' => $proceso->folios->count(),
            'posiciones_ocupadas' => $posicionesOcupadas,
            'iniciado_at' => $inicio?->toAtomString(),
            'pendiente_verificacion_at' => $proceso->pendiente_verificacion_at?->toAtomString(),
            'duracion_objetivo_minutos' => $objetivo,
            'transcurridos_minutos' => $transcurridos,
            'fin_objetivo_at' => $inicio && $objetivo !== null
                ? $inicio->addMinutes($objetivo)->toAtomString()
                : null,
            'avance_tiempo_objetivo_porcentaje' => $transcurridos !== null && $objetivo !== null
                ? min(100, $this->porcentaje($transcurridos, $objetivo))
                : null,
            'objetivo_excedido' => $objetivoExcedido,
            'minutos_sobre_objetivo' => $transcurridos !== null && $objetivo !== null
                ? max(0, $transcurridos - $objetivo)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sincronizacion(
        CarbonImmutable $ahora,
        CarbonImmutable $horaOperacional,
    ): array {
        $ultima = OperacionSincronizacion::query()
            ->with(['usuario:id,name', 'dispositivo:id,codigo,nombre'])
            ->where('recibida_servidor_at', '<=', $ahora)
            ->orderByDesc('recibida_servidor_at')
            ->orderByDesc('id')
            ->first();
        $conteos = OperacionSincronizacion::query()
            ->where('recibida_servidor_at', '>=', $horaOperacional->startOfDay()->utc())
            ->where('recibida_servidor_at', '<=', $ahora)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'estado' => $ultima?->estado->value ?? 'sin_actividad',
            'ultima_operacion' => $ultima ? [
                'id' => $ultima->id,
                'tipo' => $ultima->tipo,
                'estado' => $ultima->estado->value,
                'recibida_servidor_at' => $ultima->recibida_servidor_at?->toAtomString(),
                'procesada_at' => $ultima->procesada_at?->toAtomString(),
                'usuario' => $ultima->usuario ? [
                    'id' => $ultima->usuario->id,
                    'nombre' => $ultima->usuario->name,
                ] : null,
                'dispositivo' => $ultima->dispositivo ? [
                    'id' => $ultima->dispositivo->id,
                    'codigo' => $ultima->dispositivo->codigo,
                    'nombre' => $ultima->dispositivo->nombre,
                ] : null,
            ] : null,
            'operaciones_hoy' => collect(EstadoOperacionSincronizacion::cases())
                ->mapWithKeys(fn (EstadoOperacionSincronizacion $estado): array => [
                    $estado->value => (int) $conteos->get($estado->value, 0),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function camaras(CarbonImmutable $ahora): array
    {
        $camaras = Camara::query()
            ->where('estado', EstadoCamara::Activa->value)
            ->withCount([
                'posiciones as posiciones_operativas_count' => fn (Builder $consulta): Builder => $consulta
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereColumn('banda', '<=', 'camaras.cantidad_bandas')
                    ->whereColumn('posicion', '<=', 'camaras.posiciones_por_banda')
                    ->whereColumn('nivel', '<=', 'camaras.cantidad_niveles'),
                'posiciones as posiciones_ocupadas_count' => fn (Builder $consulta): Builder => $consulta
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereHas('ubicacionActual')
                    ->whereColumn('banda', '<=', 'camaras.cantidad_bandas')
                    ->whereColumn('posicion', '<=', 'camaras.posiciones_por_banda')
                    ->whereColumn('nivel', '<=', 'camaras.cantidad_niveles'),
            ])
            ->orderBy('codigo')
            ->get();
        $controles = $this->ultimosControlesAmbientales(
            $camaras
                ->filter(fn (Camara $camara): bool => $camara->contenido === ContenidoCamara::Productos)
                ->pluck('id'),
            $ahora,
        );

        return $camaras->map(function (Camara $camara) use ($controles, $ahora): array {
            $capacidad = (int) $camara->posiciones_operativas_count;
            $ocupadas = min($capacidad, (int) $camara->posiciones_ocupadas_count);
            $ocupacion = $this->porcentaje($ocupadas, $capacidad);
            $nivel = $ocupacion > 90
                ? 'critica'
                : ($ocupacion >= 70 ? 'advertencia' : 'normal');
            /** @var RegistroControlAmbiental|null $control */
            $control = $controles->get($camara->id);
            $estadoControl = $camara->contenido !== ContenidoCamara::Productos
                ? null
                : ($control === null
                    ? 'pendiente'
                    : ($control->estaVigente($ahora) ? 'vigente' : 'vencido'));
            $alertas = [];

            if ($nivel !== 'normal') {
                $alertas[] = $nivel === 'critica' ? 'ocupacion_critica' : 'ocupacion_alta';
            }
            if (in_array($estadoControl, ['pendiente', 'vencido'], true)) {
                $alertas[] = 'control_ambiental_'.$estadoControl;
            }

            return [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
                'contenido' => $camara->contenido->value,
                'capacidad_operativa' => $capacidad,
                'ocupadas' => $ocupadas,
                'disponibles' => max(0, $capacidad - $ocupadas),
                'ocupacion_porcentaje' => $ocupacion,
                'nivel_ocupacion' => $nivel,
                'alertas' => $alertas,
                'control_ambiental' => $estadoControl !== null ? [
                    'estado' => $estadoControl,
                    'requiere_control' => $estadoControl !== 'vigente',
                    'capturado_at' => $control?->capturado_at?->toAtomString(),
                    'vigente_hasta' => $control?->vigenteHasta()->toAtomString(),
                    'temperaturas_c' => $control ? [
                        'inicio' => (float) $control->temperatura_inicio_c,
                        'medio' => (float) $control->temperatura_medio_c,
                        'fondo' => (float) $control->temperatura_fondo_c,
                        'promedio' => round(((float) $control->temperatura_inicio_c
                            + (float) $control->temperatura_medio_c
                            + (float) $control->temperatura_fondo_c) / 3, 2),
                    ] : null,
                ] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, string>  $camaras
     * @return Collection<string, RegistroControlAmbiental>
     */
    private function ultimosControlesAmbientales(
        Collection $camaras,
        CarbonImmutable $ahora,
    ): Collection {
        if ($camaras->isEmpty()) {
            return collect();
        }

        $ultimosTiempos = RegistroControlAmbiental::query()
            ->select('camara_id')
            ->selectRaw('MAX(capturado_at) as ultimo_capturado_at')
            ->whereIn('camara_id', $camaras)
            ->where('capturado_at', '<=', $ahora)
            ->groupBy('camara_id');

        return RegistroControlAmbiental::query()
            ->joinSub(
                $ultimosTiempos,
                'ultimos_controles',
                function (JoinClause $join): void {
                    $join->on(
                        'ultimos_controles.camara_id',
                        '=',
                        'registros_control_ambiental.camara_id',
                    )->on(
                        'ultimos_controles.ultimo_capturado_at',
                        '=',
                        'registros_control_ambiental.capturado_at',
                    );
                },
            )
            ->select('registros_control_ambiental.*')
            ->get()
            ->keyBy('camara_id');
    }

    private function porcentaje(int $cantidad, int $total): float
    {
        return $total > 0 ? round(($cantidad / $total) * 100, 1) : 0.0;
    }
}
