<?php

namespace App\Services\Operacion;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\EstadoPosicion;
use App\Models\Camara;
use App\Models\OperacionSincronizacion;
use App\Models\RegistroControlAmbiental;
use App\Models\Temporada;
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
            'camaras' => $this->camaras($ahora),
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
