<?php

namespace App\Services\Gerencia;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoAdministrativoTunelPrefrio;
use App\Enums\EstadoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoDespachoMaterial;
use App\Enums\EstadoLoteMateriaPrima;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\EstadoRecepcionMaterial;
use App\Enums\EstadoRecepcionRomana;
use App\Enums\EstadoRevisionMovimientoEnvase;
use App\Enums\EstadoTecnicoTunelPrefrio;
use App\Enums\EstadoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\DespachoMaterial;
use App\Models\Folio;
use App\Models\LoteMateriaPrima;
use App\Models\MovimientoEnvase;
use App\Models\ProcesoPrefrio;
use App\Models\RecepcionMaterial;
use App\Models\RecepcionRomana;
use App\Models\TunelPrefrio;
use App\Models\ValidacionPallet;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServicioPanelGerencial
{
    public const CLAVE_CACHE = 'gerencia:panel:resumen:v2';

    private const CLAVE_BLOQUEO = 'gerencia:panel:resumen:bloqueo';

    /**
     * @return array<string, mixed>
     */
    public function obtener(): array
    {
        $instantanea = Cache::get(self::CLAVE_CACHE);

        if (is_array($instantanea)) {
            return $instantanea;
        }

        try {
            return Cache::lock(self::CLAVE_BLOQUEO, 15)->block(
                5,
                fn (): array => Cache::remember(
                    self::CLAVE_CACHE,
                    $this->segundosCache(),
                    fn (): array => $this->construir(),
                ),
            );
        } catch (LockTimeoutException) {
            return Cache::remember(
                self::CLAVE_CACHE,
                $this->segundosCache(),
                fn (): array => $this->construir(),
            );
        }
    }

    public function invalidar(): void
    {
        Cache::forget(self::CLAVE_CACHE);
    }

    /**
     * @return array<string, mixed>
     */
    private function construir(): array
    {
        $camaras = $this->camaras();
        $productos = $this->productos();
        $cargas = $this->cargas();
        $validacion = $this->validacion();
        $materiales = $this->materiales();
        $prefrio = $this->prefrio();
        $romana = $this->romana();
        $materiaPrima = $this->materiaPrima();
        $envases = $this->envases();

        return [
            'generado_at' => now()->toAtomString(),
            'actualizacion_segundos' => 30,
            'camaras' => $camaras,
            'productos' => $productos,
            'cargas' => $cargas,
            'validacion' => $validacion,
            'materiales' => $materiales,
            'prefrio' => $prefrio,
            'romana' => $romana,
            'materia_prima' => $materiaPrima,
            'envases' => $envases,
            'alertas' => $this->alertas(
                $camaras,
                $productos,
                $cargas,
                $validacion,
                $materiales,
                $prefrio,
                $romana,
                $materiaPrima,
                $envases,
            ),
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
                'materia_prima' => $this->resumenCapacidad(
                    $camaras->where('contenido', ContenidoCamara::MateriaPrima->value),
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
        $pendientesUbicacion = (clone $base)
            ->where('estado_operacional', EstadoOperacionalFolio::PendienteUbicacion->value)
            ->count();
        $ingresadosHoy = (clone $base)
            ->where('fecha_ingreso', '>=', now()->startOfDay())
            ->count();
        $pallets = (clone $base)->where('tipo_bulto', TipoBulto::Pallet->value)->count();
        $saldos = (clone $base)->where('tipo_bulto', TipoBulto::Saldo->value)->count();

        return [
            'total_activos' => $total,
            'disponibles_despacho' => $disponibles,
            'comprometidos_carga' => $comprometidos,
            'pendientes_prefrio' => $pendientes,
            'bloqueados' => $bloqueados,
            'pendientes_ubicacion' => $pendientesUbicacion,
            'ingresados_hoy' => $ingresadosHoy,
            'pallets' => $pallets,
            'saldos' => $saldos,
            'otros' => max(
                0,
                $total - $disponibles - $comprometidos - $pendientes - $bloqueados - $pendientesUbicacion,
            ),
            'disponibilidad_porcentaje' => $this->porcentaje($disponibles, $total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function cargas(): array
    {
        $estadosActivos = collect(EstadoCarga::visiblesEnOperacion())->map->value->all();
        $porEstado = Carga::query()
            ->whereIn('estado', $estadosActivos)
            ->groupBy('estado')
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'estado');
        $folios = CargaFolio::query()
            ->whereHas('reservaActiva')
            ->whereHas('carga', fn (Builder $consulta): Builder => $consulta->whereIn('estado', $estadosActivos));
        $detalle = Carga::query()
            ->withCount('asignacionesActuales')
            ->with([
                'camaraObjetivo:id,codigo,nombre',
                'andenPrevisto:id,codigo,nombre',
            ])
            ->whereIn('estado', $estadosActivos)
            ->oldest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Carga $carga): array => [
                'id' => $carga->id,
                'codigo' => $carga->codigo,
                'orden_embarque' => $carga->numero_orden_externa,
                'estado' => $carga->estado->value,
                'prioridad' => $carga->prioridad?->value,
                'folios_asignados' => (int) $carga->asignaciones_actuales_count,
                'camara_objetivo' => $carga->camaraObjetivo?->codigo,
                'anden' => $carga->andenPrevisto?->codigo,
                'publicada_at' => $carga->publicada_at?->toAtomString(),
                'antiguedad_minutos' => (int) $carga->created_at->diffInMinutes(now()),
            ]);

        return [
            'activas' => (int) $porEstado->sum(),
            'pendientes' => (int) $porEstado->get(EstadoCarga::Pendiente->value, 0),
            'en_preparacion' => (int) collect([
                EstadoCarga::EnPreparacion,
                EstadoCarga::EnSeparacion,
                EstadoCarga::DespachoParcial,
            ])->sum(fn (EstadoCarga $estado): int => (int) $porEstado->get($estado->value, 0)),
            'separadas' => (int) collect([
                EstadoCarga::Separada,
                EstadoCarga::SeparacionCompleta,
            ])->sum(fn (EstadoCarga $estado): int => (int) $porEstado->get($estado->value, 0)),
            'folios_pendientes' => (clone $folios)
                ->whereIn('estado', [
                    EstadoCargaFolio::Pendiente->value,
                    EstadoCargaFolio::ConIncidencia->value,
                ])
                ->count(),
            'folios_en_anden' => (clone $folios)
                ->where('estado', EstadoCargaFolio::EnAnden->value)
                ->count(),
            'folios_con_incidencia' => (clone $folios)
                ->where('estado', EstadoCargaFolio::ConIncidencia->value)
                ->count(),
            'cerradas_hoy' => Carga::query()
                ->whereIn('estado', [EstadoCarga::Despachada->value, EstadoCarga::Cerrada->value])
                ->where('cerrada_at', '>=', now()->startOfDay())
                ->count(),
            'detalle' => $detalle->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validacion(): array
    {
        $inicio = now()->startOfDay();
        $baseHoy = ValidacionPallet::query()->where('recibido_servidor_at', '>=', $inicio);
        $porResultado = (clone $baseHoy)
            ->groupBy('resultado')
            ->select('resultado')
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'resultado');
        $ultima = ValidacionPallet::query()
            ->latest('recibido_servidor_at')
            ->first(['numero_folio', 'resultado', 'recibido_servidor_at']);

        return [
            'procesados_hoy' => (clone $baseHoy)->count(),
            'aprobados_hoy' => (int) $porResultado->get(ResultadoValidacionPallet::Aprobado->value, 0),
            'observados_hoy' => (int) $porResultado->get(ResultadoValidacionPallet::Observado->value, 0),
            'rechazados_hoy' => (int) $porResultado->get(ResultadoValidacionPallet::Rechazado->value, 0),
            'conflictos_hoy' => (clone $baseHoy)
                ->where('estado', EstadoValidacionPallet::Conflicto->value)
                ->count(),
            'ultima_validacion' => $ultima ? [
                'folio' => $ultima->numero_folio,
                'resultado' => $ultima->resultado->value,
                'recibido_at' => $ultima->recibido_servidor_at?->toAtomString(),
            ] : null,
        ];
    }

    private function materiales(): array
    {
        $filas = DB::table('folios_materiales as fm')
            ->join('folios as f', 'f.id', '=', 'fm.folio_id')
            ->join('items_materiales as i', 'i.id', '=', 'fm.item_material_id')
            ->join('clientes_materiales as cm', 'cm.id', '=', 'i.cliente_material_id')
            ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
            ->leftJoin('ubicaciones_actuales as ua', 'ua.folio_id', '=', 'f.id')
            ->leftJoin('posiciones as p', 'p.id', '=', 'ua.posicion_id')
            ->leftJoin('camaras as c', 'c.id', '=', 'p.camara_id')
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
            ->selectRaw(
                'SUM(CASE WHEN (f.estado_operacional = ? OR fm.motivo_bloqueo IS NOT NULL) '
                .'THEN fm.cantidad_actual ELSE 0 END) as cantidad_bloqueada',
                [EstadoOperacionalFolio::Bloqueado->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN f.estado_operacional <> ? '
                .'AND fm.motivo_bloqueo IS NULL '
                .'AND (ua.id IS NULL OR f.estado_operacional = ?) '
                .'THEN fm.cantidad_actual ELSE 0 END) as cantidad_pendiente_ubicacion',
                [
                    EstadoOperacionalFolio::Bloqueado->value,
                    EstadoOperacionalFolio::PendienteUbicacion->value,
                ],
            )
            ->selectRaw(
                'SUM(CASE WHEN f.estado_operacional = ? '
                .'AND fm.motivo_bloqueo IS NULL '
                .'AND tm.activa = ? '
                .'AND ua.id IS NOT NULL '
                .'AND p.estado = ? '
                .'AND c.estado = ? '
                .'AND c.contenido = ? '
                .'THEN LEAST(fm.cantidad_reservada, fm.cantidad_actual) ELSE 0 END) '
                .'as cantidad_reservada',
                [
                    EstadoOperacionalFolio::Disponible->value,
                    true,
                    EstadoPosicion::Activa->value,
                    EstadoCamara::Activa->value,
                    ContenidoCamara::Materiales->value,
                ],
            )
            ->selectRaw(
                'SUM(CASE WHEN f.estado_operacional = ? '
                .'AND fm.motivo_bloqueo IS NULL '
                .'AND tm.activa = ? '
                .'AND ua.id IS NOT NULL '
                .'AND p.estado = ? '
                .'AND c.estado = ? '
                .'AND c.contenido = ? '
                .'THEN GREATEST(fm.cantidad_actual - fm.cantidad_reservada, 0) ELSE 0 END) '
                .'as cantidad_disponible',
                [
                    EstadoOperacionalFolio::Disponible->value,
                    true,
                    EstadoPosicion::Activa->value,
                    EstadoCamara::Activa->value,
                    ContenidoCamara::Materiales->value,
                ],
            )
            ->get()
            ->map(function (object $fila): array {
                $actual = round((float) $fila->cantidad_actual, 3);
                $reservada = round((float) $fila->cantidad_reservada, 3);
                $disponible = round((float) $fila->cantidad_disponible, 3);
                $bloqueada = round((float) $fila->cantidad_bloqueada, 3);
                $pendiente = round((float) $fila->cantidad_pendiente_ubicacion, 3);
                $noDisponible = round(max(
                    0,
                    $actual - $reservada - $disponible - $bloqueada - $pendiente,
                ), 3);

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
                    'cantidad_disponible' => $disponible,
                    'cantidad_bloqueada' => $bloqueada,
                    'cantidad_pendiente_ubicacion' => $pendiente,
                    'cantidad_no_disponible' => $noDisponible,
                ];
            });

        $porUnidad = $filas
            ->groupBy('unidad_medida')
            ->map(function (Collection $items, string $unidad): array {
                $actual = round((float) $items->sum('cantidad_actual'), 3);
                $reservada = round((float) $items->sum('cantidad_reservada'), 3);
                $disponible = round((float) $items->sum('cantidad_disponible'), 3);
                $bloqueada = round((float) $items->sum('cantidad_bloqueada'), 3);
                $pendiente = round((float) $items->sum('cantidad_pendiente_ubicacion'), 3);
                $noDisponible = round((float) $items->sum('cantidad_no_disponible'), 3);

                return [
                    'unidad_medida' => $unidad,
                    'items_con_stock' => $items->count(),
                    'folios_con_stock' => (int) $items->sum('folios'),
                    'cantidad_actual' => $actual,
                    'cantidad_reservada' => $reservada,
                    'cantidad_disponible' => $disponible,
                    'cantidad_bloqueada' => $bloqueada,
                    'cantidad_pendiente_ubicacion' => $pendiente,
                    'cantidad_no_disponible' => $noDisponible,
                    'items' => $items
                        ->sortByDesc('cantidad_actual')
                        ->values()
                        ->all(),
                ];
            })
            ->sortKeys()
            ->values();

        return [
            'items_con_stock' => $filas->pluck('item_id')->unique()->count(),
            'folios_con_stock' => (int) $filas->sum('folios'),
            'despachos_abiertos' => DespachoMaterial::query()
                ->whereIn('estado', [
                    EstadoDespachoMaterial::Pendiente->value,
                    EstadoDespachoMaterial::Parcial->value,
                ])
                ->count(),
            'despachos_parciales' => DespachoMaterial::query()
                ->where('estado', EstadoDespachoMaterial::Parcial->value)
                ->count(),
            'recepciones_confirmadas_hoy' => RecepcionMaterial::query()
                ->where('estado', EstadoRecepcionMaterial::Confirmada->value)
                ->where('confirmado_at', '>=', now()->startOfDay())
                ->count(),
            'recepciones_borrador' => RecepcionMaterial::query()
                ->where('estado', EstadoRecepcionMaterial::Borrador->value)
                ->count(),
            'unidades_medida' => $porUnidad->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function materiaPrima(): array
    {
        $base = LoteMateriaPrima::query()
            ->where('estado', '!=', EstadoLoteMateriaPrima::Anulado->value);
        $porEstado = (clone $base)
            ->groupBy('estado')
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'estado');

        return [
            'lotes_activos' => (clone $base)->count(),
            'borradores' => (int) $porEstado->get(EstadoLoteMateriaPrima::Borrador->value, 0),
            'pendientes_hidrocooler' => (int) $porEstado->get(
                EstadoLoteMateriaPrima::PendienteHidrocooler->value,
                0,
            ),
            'hidrocooler_en_curso' => (int) $porEstado->get(
                EstadoLoteMateriaPrima::HidrocoolerEnCurso->value,
                0,
            ),
            'pendientes_asignacion' => (int) $porEstado->get(
                EstadoLoteMateriaPrima::PendienteAsignacion->value,
                0,
            ),
            'en_camara' => (int) $porEstado->get(EstadoLoteMateriaPrima::AsignadoCamara->value, 0),
            'entrega_parcial' => (int) $porEstado->get(
                EstadoLoteMateriaPrima::EntregaParcialProceso->value,
                0,
            ),
            'confirmados_hoy' => LoteMateriaPrima::query()
                ->whereNotNull('confirmado_at')
                ->where('confirmado_at', '>=', now()->startOfDay())
                ->count(),
            'kilos_confirmados_hoy' => round((float) LoteMateriaPrima::query()
                ->whereNotNull('confirmado_at')
                ->where('confirmado_at', '>=', now()->startOfDay())
                ->sum('kilos_netos_confirmados'), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function envases(): array
    {
        $hoy = MovimientoEnvase::query()->where('ocurrido_at', '>=', now()->startOfDay());

        return [
            'movimientos_hoy' => (clone $hoy)->count(),
            'unidades_movidas_hoy' => (int) (clone $hoy)->sum('cantidad'),
            'pendientes_revision' => MovimientoEnvase::query()
                ->where('estado_revision', EstadoRevisionMovimientoEnvase::Pendiente->value)
                ->count(),
            'observados' => MovimientoEnvase::query()
                ->where('estado_revision', EstadoRevisionMovimientoEnvase::Observado->value)
                ->count(),
        ];
    }

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
                'procesoActivo' => fn (HasOne $consulta): HasOne => $consulta
                    ->withCount([
                        'folios as folios_activos_count' => fn (Builder $folios): Builder => $folios
                            ->whereNull('retirado_at'),
                    ]),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(function (TunelPrefrio $tunel): array {
                $operativo = $tunel->estado_administrativo === EstadoAdministrativoTunelPrefrio::Activo
                    && $tunel->estado_tecnico === EstadoTecnicoTunelPrefrio::Operativo;
                $capacidad = $operativo ? (int) $tunel->posiciones_activas_count : 0;
                $ocupadas = $operativo
                    ? min($capacidad, (int) ($tunel->procesoActivo?->folios_activos_count ?? 0))
                    : 0;
                $proceso = $tunel->procesoActivo;
                $transcurridos = $proceso?->iniciado_at
                    ? (int) $proceso->iniciado_at->diffInMinutes(now())
                    : null;
                $objetivo = $proceso?->duracion_objetivo_minutos;
                $atrasado = $transcurridos !== null
                    && $objetivo
                    && $transcurridos > $objetivo
                    && in_array($proceso->estado, [
                        EstadoProcesoPrefrio::EnProceso,
                        EstadoProcesoPrefrio::PendienteVerificacion,
                    ], true);

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
                    'proceso_activo' => $proceso ? [
                        'codigo' => $proceso->codigo,
                        'estado' => $proceso->estado->value,
                        'setpoint' => $proceso->setpoint !== null ? (float) $proceso->setpoint : null,
                        'formato' => $proceso->formato_referencia,
                        'iniciado_at' => $proceso->iniciado_at?->toAtomString(),
                        'duracion_objetivo_minutos' => $objetivo,
                        'transcurridos_minutos' => $transcurridos,
                        'atrasado' => $atrasado,
                        'avance_porcentaje' => $transcurridos !== null && $objetivo
                            ? min(100, $this->porcentaje($transcurridos, $objetivo))
                            : null,
                    ] : null,
                ];
            });
        $capacidad = (int) $tuneles->sum('capacidad');
        $ocupadas = (int) $tuneles->sum('ocupadas');
        $completados = ProcesoPrefrio::query()
            ->whereIn('estado', [
                EstadoProcesoPrefrio::Aprobado->value,
                EstadoProcesoPrefrio::RequiereReproceso->value,
            ])
            ->whereNotNull('iniciado_at')
            ->whereNotNull('finalizado_at')
            ->where('finalizado_at', '>=', now()->subDays(7))
            ->get(['estado', 'iniciado_at', 'finalizado_at']);
        $duraciones = $completados
            ->map(fn (ProcesoPrefrio $proceso): int => (int) $proceso->iniciado_at->diffInMinutes($proceso->finalizado_at))
            ->filter(fn (int $minutos): bool => $minutos >= 0);

        return [
            'tuneles_operativos' => $tuneles->where('operativo', true)->count(),
            'tuneles_totales' => $tuneles->count(),
            'procesos_activos' => ProcesoPrefrio::query()->whereIn('estado', $estadosActivos)->count(),
            'procesos_atrasados' => $tuneles
                ->filter(fn (array $tunel): bool => (bool) ($tunel['proceso_activo']['atrasado'] ?? false))
                ->count(),
            'aprobados_hoy' => $completados
                ->where('estado', EstadoProcesoPrefrio::Aprobado)
                ->filter(fn (ProcesoPrefrio $proceso): bool => $proceso->finalizado_at->isToday())
                ->count(),
            'reprocesos_hoy' => $completados
                ->where('estado', EstadoProcesoPrefrio::RequiereReproceso)
                ->filter(fn (ProcesoPrefrio $proceso): bool => $proceso->finalizado_at->isToday())
                ->count(),
            'duracion_promedio_minutos_7d' => $duraciones->isNotEmpty()
                ? (int) round($duraciones->average())
                : null,
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
        $inicio = $hoy->subDays(6)->startOfDay();
        $termino = $hoy->addDay()->startOfDay();
        $porEstado = RecepcionRomana::query()
            ->whereIn('estado', [
                EstadoRecepcionRomana::EnBasculaIngreso->value,
                EstadoRecepcionRomana::EnPesajeEnvases->value,
                EstadoRecepcionRomana::EnBasculaSalida->value,
            ])
            ->groupBy('estado')
            ->select('estado')
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'estado');
        $porDia = RecepcionRomana::query()
            ->where('estado', EstadoRecepcionRomana::Cerrado->value)
            ->where('salida_at', '>=', $inicio)
            ->where('salida_at', '<', $termino)
            ->groupByRaw('DATE(salida_at)')
            ->selectRaw('DATE(salida_at) as fecha')
            ->selectRaw('COUNT(*) as recepciones')
            ->selectRaw('COALESCE(SUM(peso_neto), 0) as peso_neto')
            ->selectRaw('COALESCE(SUM(cantidad_envases_declarados), 0) as envases')
            ->selectRaw('COUNT(DISTINCT cliente_id) as clientes')
            ->get()
            ->keyBy('fecha');
        $tendencia = collect(range(6, 0))
            ->map(function (int $dias) use ($hoy, $porDia): array {
                $fecha = $hoy->subDays($dias);
                $fila = $porDia->get($fecha->toDateString());

                return [
                    'fecha' => $fecha->toDateString(),
                    'etiqueta' => $fecha->locale('es')->isoFormat('ddd D'),
                    'recepciones' => (int) ($fila?->recepciones ?? 0),
                    'peso_neto' => round((float) ($fila?->peso_neto ?? 0), 2),
                ];
            });
        $filaHoy = $porDia->get($hoy->toDateString());

        return [
            'en_bascula_ingreso' => (int) $porEstado->get(
                EstadoRecepcionRomana::EnBasculaIngreso->value,
                0,
            ),
            'en_pesaje_envases' => (int) $porEstado->get(
                EstadoRecepcionRomana::EnPesajeEnvases->value,
                0,
            ),
            'pendientes_destare' => (int) $porEstado->get(
                EstadoRecepcionRomana::EnBasculaSalida->value,
                0,
            ),
            'cerradas_hoy' => (int) ($filaHoy?->recepciones ?? 0),
            'peso_neto_hoy' => round((float) ($filaHoy?->peso_neto ?? 0), 2),
            'envases_hoy' => (int) ($filaHoy?->envases ?? 0),
            'clientes_hoy' => (int) ($filaHoy?->clientes ?? 0),
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
     * @param  array<string, mixed>  $productos
     * @param  array<string, mixed>  $cargas
     * @param  array<string, mixed>  $validacion
     * @param  array<string, mixed>  $materiales
     * @param  array<string, mixed>  $prefrio
     * @param  array<string, mixed>  $romana
     * @param  array<string, mixed>  $materiaPrima
     * @param  array<string, mixed>  $envases
     * @return array<int, array<string, string|int|float>>
     */
    private function alertas(
        array $camaras,
        array $productos,
        array $cargas,
        array $validacion,
        array $materiales,
        array $prefrio,
        array $romana,
        array $materiaPrima,
        array $envases,
    ): array {
        $alertas = collect($camaras['detalle'])
            ->filter(fn (array $camara): bool => $camara['ocupacion_porcentaje'] >= 90)
            ->map(fn (array $camara): array => [
                'nivel' => 'advertencia',
                'area' => 'Cámaras',
                'titulo' => "{$camara['codigo']} con alta ocupación",
                'detalle' => "{$camara['ocupadas']} de {$camara['operativas']} posiciones operativas ocupadas.",
                'metrica' => "{$camara['ocupacion_porcentaje']}%",
                'href' => '/oficina/frigorifico/camaras',
            ]);

        collect($prefrio['tuneles'])
            ->where('operativo', false)
            ->each(fn (array $tunel) => $alertas->push([
                'nivel' => 'critica',
                'area' => 'Prefrío',
                'titulo' => "{$tunel['codigo']} no disponible",
                'detalle' => 'El túnel está inactivo, en mantenimiento o fuera de servicio.',
                'metrica' => 'Fuera de servicio',
                'href' => '/oficina/prefrio',
            ]));

        if ($prefrio['procesos_atrasados'] > 0) {
            $alertas->push([
                'nivel' => 'critica',
                'area' => 'Prefrío',
                'titulo' => 'Procesos sobre su duración objetivo',
                'detalle' => "{$prefrio['procesos_atrasados']} proceso(s) exceden el tiempo configurado.",
                'metrica' => $prefrio['procesos_atrasados'],
                'href' => '/oficina/prefrio',
            ]);
        }

        if ($productos['bloqueados'] > 0) {
            $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Producto terminado',
                'titulo' => 'Folios bloqueados',
                'detalle' => "{$productos['bloqueados']} folio(s) requieren liberación o corrección.",
                'metrica' => $productos['bloqueados'],
                'href' => '/oficina/frigorifico/camaras',
            ]);
        }

        if ($cargas['folios_con_incidencia'] > 0) {
            $alertas->push([
                'nivel' => 'critica',
                'area' => 'Cargas',
                'titulo' => 'Folios con incidencia de despacho',
                'detalle' => "{$cargas['folios_con_incidencia']} folio(s) frenan o condicionan cargas activas.",
                'metrica' => $cargas['folios_con_incidencia'],
                'href' => '/oficina/cargas',
            ]);
        }

        if ($validacion['observados_hoy'] + $validacion['rechazados_hoy'] > 0) {
            $total = $validacion['observados_hoy'] + $validacion['rechazados_hoy'];
            $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Validación PT',
                'titulo' => 'Pallets observados o rechazados hoy',
                'detalle' => "{$validacion['observados_hoy']} observados y {$validacion['rechazados_hoy']} rechazados.",
                'metrica' => $total,
                'href' => '/oficina/validacion',
            ]);
        }

        if ($validacion['conflictos_hoy'] > 0) {
            $alertas->push([
                'nivel' => 'critica',
                'area' => 'Validación PT',
                'titulo' => 'Conflictos de sincronización',
                'detalle' => "{$validacion['conflictos_hoy']} validación(es) presentan conflicto de datos.",
                'metrica' => $validacion['conflictos_hoy'],
                'href' => '/oficina/validacion',
            ]);
        }

        collect($materiales['unidades_medida'])
            ->filter(fn (array $unidad): bool => $unidad['cantidad_actual'] > 0
                && $unidad['cantidad_disponible'] <= 0)
            ->each(fn (array $unidad) => $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Materiales',
                'titulo' => "Stock {$unidad['unidad_medida']} sin disponibilidad operativa",
                'detalle' => 'No queda cantidad habilitada para nuevos despachos en esta unidad de medida.',
                'metrica' => $unidad['cantidad_actual'],
                'href' => '/oficina/materiales/inventario',
            ]));

        collect($materiales['unidades_medida'])
            ->filter(fn (array $unidad): bool => $unidad['cantidad_bloqueada'] > 0)
            ->each(fn (array $unidad) => $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Materiales',
                'titulo' => "Material {$unidad['unidad_medida']} bloqueado",
                'detalle' => "{$unidad['cantidad_bloqueada']} {$unidad['unidad_medida']} requieren revisión supervisada.",
                'metrica' => $unidad['cantidad_bloqueada'],
                'href' => '/oficina/materiales/inventario',
            ]));

        collect($materiales['unidades_medida'])
            ->filter(fn (array $unidad): bool => $unidad['cantidad_pendiente_ubicacion'] > 0)
            ->each(fn (array $unidad) => $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Materiales',
                'titulo' => "Material {$unidad['unidad_medida']} sin ubicación",
                'detalle' => "{$unidad['cantidad_pendiente_ubicacion']} {$unidad['unidad_medida']} todavía no están disponibles para operación.",
                'metrica' => $unidad['cantidad_pendiente_ubicacion'],
                'href' => '/oficina/materiales/inventario',
            ]));

        if ($materiaPrima['pendientes_hidrocooler'] + $materiaPrima['pendientes_asignacion'] > 0) {
            $total = $materiaPrima['pendientes_hidrocooler'] + $materiaPrima['pendientes_asignacion'];
            $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Materia prima',
                'titulo' => 'Lotes pendientes de etapa',
                'detalle' => "{$materiaPrima['pendientes_hidrocooler']} esperan hidrocooler y {$materiaPrima['pendientes_asignacion']} esperan cámara.",
                'metrica' => $total,
                'href' => '/oficina/materia-prima',
            ]);
        }

        if ($romana['pendientes_destare'] > 0) {
            $alertas->push([
                'nivel' => 'advertencia',
                'area' => 'Romana',
                'titulo' => 'Recepciones pendientes de cierre',
                'detalle' => "{$romana['pendientes_destare']} recepción(es) esperan destare o cierre documental.",
                'metrica' => $romana['pendientes_destare'],
                'href' => '/oficina/romana',
            ]);
        }

        if ($envases['pendientes_revision'] > 0) {
            $alertas->push([
                'nivel' => 'informativa',
                'area' => 'Envases',
                'titulo' => 'Movimientos pendientes de revisión',
                'detalle' => "{$envases['pendientes_revision']} movimiento(s) requieren chequeo documental.",
                'metrica' => $envases['pendientes_revision'],
                'href' => '/oficina/envases/cuenta-corriente',
            ]);
        }

        $prioridad = ['critica' => 0, 'advertencia' => 1, 'informativa' => 2];

        return $alertas
            ->sortBy(fn (array $alerta): int => $prioridad[$alerta['nivel']] ?? 3)
            ->values()
            ->all();
    }

    private function porcentaje(int|float $parte, int|float $total): float
    {
        return $total > 0 ? round(($parte / $total) * 100, 1) : 0.0;
    }

    private function segundosCache(): int
    {
        return max(1, (int) config('gerencia.cache_segundos', 60));
    }
}
