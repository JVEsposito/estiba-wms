<?php

namespace App\Services\Gerencia;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoAdministrativoTunelPrefrio;
use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\EstadoRecepcionRomana;
use App\Enums\EstadoTecnicoTunelPrefrio;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Folio;
use App\Models\ProcesoPrefrio;
use App\Models\RecepcionRomana;
use App\Models\TunelPrefrio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioPanelGerencial
{
    /**
     * @return array<string, mixed>
     */
    public function obtener(): array
    {
        $camaras = $this->camaras();
        $productos = $this->productos();
        $materiales = $this->materiales();
        $prefrio = $this->prefrio();
        $romana = $this->romana();

        return [
            'generado_at' => now()->toAtomString(),
            'actualizacion_segundos' => 30,
            'camaras' => $camaras,
            'productos' => $productos,
            'materiales' => $materiales,
            'prefrio' => $prefrio,
            'romana' => $romana,
            'alertas' => $this->alertas($camaras, $materiales, $prefrio, $romana),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function camaras(): array
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
                'posiciones as posiciones_no_operativas_count' => fn (Builder $consulta): Builder => $consulta
                    ->where('estado', '!=', EstadoPosicion::Activa->value)
                    ->whereColumn('banda', '<=', 'camaras.cantidad_bandas')
                    ->whereColumn('posicion', '<=', 'camaras.posiciones_por_banda')
                    ->whereColumn('nivel', '<=', 'camaras.cantidad_niveles'),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(function (Camara $camara): array {
                $operativas = (int) $camara->posiciones_operativas_count;
                $ocupadas = min($operativas, (int) $camara->posiciones_ocupadas_count);

                return [
                    'id' => $camara->id,
                    'codigo' => $camara->codigo,
                    'nombre' => $camara->nombre,
                    'contenido' => $camara->contenido->value,
                    'operativas' => $operativas,
                    'ocupadas' => $ocupadas,
                    'disponibles' => max(0, $operativas - $ocupadas),
                    'no_operativas' => (int) $camara->posiciones_no_operativas_count,
                    'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $operativas),
                ];
            });

        return [
            'resumen' => $this->resumenCapacidad($camaras),
            'por_contenido' => [
                'productos' => $this->resumenCapacidad(
                    $camaras->where('contenido', ContenidoCamara::Productos->value),
                ),
                'materiales' => $this->resumenCapacidad(
                    $camaras->where('contenido', ContenidoCamara::Materiales->value),
                ),
            ],
            'detalle' => $camaras->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productos(): array
    {
        $base = Folio::query()
            ->where('activo', true)
            ->whereIn('tipo_bulto', [TipoBulto::Pallet->value, TipoBulto::Saldo->value]);
        $total = (clone $base)->count();
        $disponibles = (clone $base)
            ->where('estado_operacional', EstadoOperacionalFolio::Disponible->value)
            ->whereDoesntHave('asignacionCargaActual')
            ->whereHas(
                'ubicacionActual.posicion',
                fn (Builder $posicion): Builder => $posicion
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereHas(
                        'camara',
                        fn (Builder $camara): Builder => $camara
                            ->where('estado', EstadoCamara::Activa->value)
                            ->where('contenido', ContenidoCamara::Productos->value),
                    ),
            )
            ->count();
        $comprometidos = (clone $base)
            ->where('estado_operacional', EstadoOperacionalFolio::Disponible->value)
            ->whereHas('asignacionCargaActual')
            ->count();
        $pendientes = (clone $base)
            ->where('estado_operacional', EstadoOperacionalFolio::PendientePrefrio->value)
            ->count();
        $bloqueados = (clone $base)
            ->where('estado_operacional', EstadoOperacionalFolio::Bloqueado->value)
            ->count();

        return [
            'total_activos' => $total,
            'disponibles_despacho' => $disponibles,
            'comprometidos_carga' => $comprometidos,
            'pendientes_prefrio' => $pendientes,
            'bloqueados' => $bloqueados,
            'otros' => max(0, $total - $disponibles - $comprometidos - $pendientes - $bloqueados),
            'disponibilidad_porcentaje' => $this->porcentaje($disponibles, $total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function materiales(): array
    {
        $filas = DB::table('folios_materiales as fm')
            ->join('folios as f', 'f.id', '=', 'fm.folio_id')
            ->join('items_materiales as i', 'i.id', '=', 'fm.item_material_id')
            ->join('clientes_materiales as cm', 'cm.id', '=', 'i.cliente_material_id')
            ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
            ->where('f.activo', true)
            ->where('i.activo', true)
            ->where('fm.cantidad_actual', '>', 0)
            ->groupBy([
                'fm.item_material_id',
                'cm.id',
                'cm.codigo',
                'cm.nombre',
                'tm.id',
                'tm.codigo',
                'tm.nombre',
                'tm.activa',
                'i.codigo',
                'i.nombre',
                'i.categoria',
                'fm.unidad_medida',
            ])
            ->select([
                'fm.item_material_id',
                'cm.id as cliente_id',
                'cm.codigo as cliente_codigo',
                'cm.nombre as cliente_nombre',
                'tm.id as temporada_id',
                'tm.codigo as temporada_codigo',
                'tm.nombre as temporada_nombre',
                'tm.activa as temporada_activa',
                'i.codigo',
                'i.nombre',
                'i.categoria',
                'fm.unidad_medida',
            ])
            ->selectRaw('COUNT(DISTINCT fm.folio_id) as folios')
            ->selectRaw('SUM(fm.cantidad_actual) as cantidad_actual')
            ->selectRaw('SUM(fm.cantidad_reservada) as cantidad_reservada')
            ->get()
            ->map(function (object $fila): array {
                $actual = round((float) $fila->cantidad_actual, 3);
                $reservada = round((float) $fila->cantidad_reservada, 3);

                return [
                    'item_id' => $fila->item_material_id,
                    'cliente' => [
                        'id' => $fila->cliente_id,
                        'codigo' => $fila->cliente_codigo,
                        'nombre' => $fila->cliente_nombre,
                    ],
                    'temporada' => [
                        'id' => $fila->temporada_id,
                        'codigo' => $fila->temporada_codigo,
                        'nombre' => $fila->temporada_nombre,
                        'activa' => (bool) $fila->temporada_activa,
                    ],
                    'codigo' => $fila->codigo,
                    'nombre' => $fila->nombre,
                    'categoria' => $fila->categoria ?: 'Sin categoría',
                    'unidad_medida' => $fila->unidad_medida,
                    'folios' => (int) $fila->folios,
                    'cantidad_actual' => $actual,
                    'cantidad_reservada' => $reservada,
                    'cantidad_disponible' => round(max(0, $actual - $reservada), 3),
                ];
            });

        $porUnidad = $filas
            ->groupBy('unidad_medida')
            ->map(function (Collection $items, string $unidad): array {
                $actual = round((float) $items->sum('cantidad_actual'), 3);
                $reservada = round((float) $items->sum('cantidad_reservada'), 3);

                return [
                    'unidad_medida' => $unidad,
                    'items_con_stock' => $items->count(),
                    'folios_con_stock' => (int) $items->sum('folios'),
                    'cantidad_actual' => $actual,
                    'cantidad_reservada' => $reservada,
                    'cantidad_disponible' => round(max(0, $actual - $reservada), 3),
                    'items' => $items
                        ->sortByDesc('cantidad_disponible')
                        ->values()
                        ->all(),
                ];
            })
            ->sortKeys()
            ->values();

        return [
            'items_con_stock' => $filas->pluck('item_id')->unique()->count(),
            'folios_con_stock' => (int) $filas->sum('folios'),
            'unidades_medida' => $porUnidad->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prefrio(): array
    {
        $estadosActivos = collect(EstadoProcesoPrefrio::cases())
            ->filter->esActivo()
            ->map->value
            ->all();
        $tuneles = TunelPrefrio::query()
            ->withCount([
                'posiciones as posiciones_activas_count' => fn (Builder $consulta): Builder => $consulta
                    ->where('activa', true),
            ])
            ->with([
                'procesoActivo.folios' => fn (HasMany $consulta): HasMany => $consulta
                    ->whereNull('retirado_at'),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(function (TunelPrefrio $tunel): array {
                $operativo = $tunel->estado_administrativo === EstadoAdministrativoTunelPrefrio::Activo
                    && $tunel->estado_tecnico === EstadoTecnicoTunelPrefrio::Operativo;
                $capacidad = $operativo ? (int) $tunel->posiciones_activas_count : 0;
                $ocupadas = $operativo
                    ? min($capacidad, (int) ($tunel->procesoActivo?->folios->count() ?? 0))
                    : 0;

                return [
                    'id' => $tunel->id,
                    'codigo' => $tunel->codigo,
                    'nombre' => $tunel->nombre,
                    'estado_administrativo' => $tunel->estado_administrativo->value,
                    'estado_tecnico' => $tunel->estado_tecnico->value,
                    'operativo' => $operativo,
                    'capacidad' => $capacidad,
                    'ocupadas' => $ocupadas,
                    'disponibles' => max(0, $capacidad - $ocupadas),
                    'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $capacidad),
                    'proceso_activo' => $tunel->procesoActivo ? [
                        'codigo' => $tunel->procesoActivo->codigo,
                        'estado' => $tunel->procesoActivo->estado->value,
                    ] : null,
                ];
            });
        $capacidad = (int) $tuneles->sum('capacidad');
        $ocupadas = (int) $tuneles->sum('ocupadas');

        return [
            'tuneles_operativos' => $tuneles->where('operativo', true)->count(),
            'tuneles_totales' => $tuneles->count(),
            'procesos_activos' => ProcesoPrefrio::query()->whereIn('estado', $estadosActivos)->count(),
            'folios_pendientes' => Folio::query()
                ->where('activo', true)
                ->where('estado_operacional', EstadoOperacionalFolio::PendientePrefrio->value)
                ->whereIn('tipo_bulto', [TipoBulto::Pallet->value, TipoBulto::Saldo->value])
                ->count(),
            'capacidad' => $capacidad,
            'ocupadas' => $ocupadas,
            'disponibles' => max(0, $capacidad - $ocupadas),
            'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $capacidad),
            'tuneles' => $tuneles->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function romana(): array
    {
        $hoy = CarbonImmutable::today();
        $cerradasHoy = RecepcionRomana::query()
            ->where('estado', EstadoRecepcionRomana::Cerrado->value)
            ->whereDate('salida_at', $hoy->toDateString());
        $tendencia = collect(range(6, 0))
            ->map(function (int $dias) use ($hoy): array {
                $fecha = $hoy->subDays($dias);
                $cerradas = RecepcionRomana::query()
                    ->where('estado', EstadoRecepcionRomana::Cerrado->value)
                    ->whereDate('salida_at', $fecha->toDateString());

                return [
                    'fecha' => $fecha->toDateString(),
                    'etiqueta' => $fecha->locale('es')->isoFormat('ddd D'),
                    'recepciones' => (clone $cerradas)->count(),
                    'peso_neto' => round((float) (clone $cerradas)->sum('peso_neto'), 2),
                ];
            });

        return [
            'en_bascula_ingreso' => RecepcionRomana::query()
                ->where('estado', EstadoRecepcionRomana::EnBasculaIngreso->value)
                ->count(),
            'pendientes_destare' => RecepcionRomana::query()
                ->where('estado', EstadoRecepcionRomana::EnBasculaSalida->value)
                ->count(),
            'cerradas_hoy' => (clone $cerradasHoy)->count(),
            'peso_neto_hoy' => round((float) (clone $cerradasHoy)->sum('peso_neto'), 2),
            'envases_hoy' => (int) (clone $cerradasHoy)->sum('cantidad_envases_declarados'),
            'clientes_hoy' => (clone $cerradasHoy)->distinct()->count('cliente_id'),
            'tendencia_diaria' => $tendencia->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $camaras
     * @return array<string, int|float>
     */
    private function resumenCapacidad(Collection $camaras): array
    {
        $operativas = (int) $camaras->sum('operativas');
        $ocupadas = (int) $camaras->sum('ocupadas');

        return [
            'camaras' => $camaras->count(),
            'operativas' => $operativas,
            'ocupadas' => $ocupadas,
            'disponibles' => max(0, $operativas - $ocupadas),
            'no_operativas' => (int) $camaras->sum('no_operativas'),
            'ocupacion_porcentaje' => $this->porcentaje($ocupadas, $operativas),
        ];
    }

    /**
     * @param  array<string, mixed>  $camaras
     * @param  array<string, mixed>  $materiales
     * @param  array<string, mixed>  $prefrio
     * @param  array<string, mixed>  $romana
     * @return array<int, array<string, string>>
     */
    private function alertas(array $camaras, array $materiales, array $prefrio, array $romana): array
    {
        $alertas = collect($camaras['detalle'])
            ->filter(fn (array $camara): bool => $camara['ocupacion_porcentaje'] >= 90)
            ->map(fn (array $camara): array => [
                'nivel' => 'advertencia',
                'titulo' => "{$camara['codigo']} con alta ocupación",
                'detalle' => "{$camara['ocupadas']} de {$camara['operativas']} posiciones operativas ocupadas.",
            ]);

        collect($prefrio['tuneles'])
            ->where('operativo', false)
            ->each(function (array $tunel) use ($alertas): void {
                $alertas->push([
                    'nivel' => 'critica',
                    'titulo' => "{$tunel['codigo']} no disponible",
                    'detalle' => 'El túnel está inactivo, en mantenimiento o fuera de servicio.',
                ]);
            });

        collect($materiales['unidades_medida'])
            ->filter(fn (array $unidad): bool => $unidad['cantidad_actual'] > 0
                && $unidad['cantidad_disponible'] <= 0)
            ->each(function (array $unidad) use ($alertas): void {
                $alertas->push([
                    'nivel' => 'advertencia',
                    'titulo' => "Stock {$unidad['unidad_medida']} completamente reservado",
                    'detalle' => 'No queda cantidad libre para nuevos despachos en esta unidad de medida.',
                ]);
            });

        if ($romana['pendientes_destare'] > 0) {
            $alertas->push([
                'nivel' => 'advertencia',
                'titulo' => 'Camiones pendientes de destare',
                'detalle' => "{$romana['pendientes_destare']} recepción(es) esperan el pesaje de salida en romana.",
            ]);
        }

        return $alertas->values()->all();
    }

    private function porcentaje(int|float $parte, int|float $total): float
    {
        return $total > 0 ? round(($parte / $total) * 100, 1) : 0.0;
    }
}
