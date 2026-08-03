<?php

namespace App\Services\Existencias;

use App\Enums\CondicionTermicaFolio;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoFolioProcesoPrefrio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Models\AlmacenMaterial;
use App\Models\Folio;
use App\Models\LoteMateriaPrima;
use App\Models\SaldoMaterialAlmacen;
use App\Models\Temporada;
use App\Models\User;
use DomainException;
use Illuminate\Support\LazyCollection;

class ServicioExistencias
{
    public const PRODUCTO_TERMINADO = 'producto-terminado';

    public const MATERIALES = 'materiales';

    public const MATERIA_PRIMA = 'materia-prima';

    /** @return array<string, array<string, mixed>> */
    public function definiciones(): array
    {
        return [
            self::PRODUCTO_TERMINADO => [
                'tipo' => self::PRODUCTO_TERMINADO,
                'titulo' => 'Existencia de producto terminado',
                'descripcion' => 'Folios activos de pallets y saldos, desde Validación y Prefrío hasta cámara y despacho.',
                'archivo' => 'Existencia_Producto_Terminado',
                'columnas' => [
                    ['clave' => 'temporada', 'titulo' => 'Temporada', 'ancho' => 18],
                    ['clave' => 'folio', 'titulo' => 'Folio', 'ancho' => 24],
                    ['clave' => 'tipo_bulto', 'titulo' => 'Tipo de bulto', 'ancho' => 15],
                    ['clave' => 'estado_operacional', 'titulo' => 'Estado operacional', 'ancho' => 22],
                    ['clave' => 'etapa_actual', 'titulo' => 'Etapa actual', 'ancho' => 22],
                    ['clave' => 'cantidad_cajas', 'titulo' => 'Cantidad de cajas', 'ancho' => 16, 'tipo' => 'numero'],
                    ['clave' => 'cliente', 'titulo' => 'Cliente', 'ancho' => 22],
                    ['clave' => 'marca', 'titulo' => 'Marca', 'ancho' => 18],
                    ['clave' => 'especie', 'titulo' => 'Especie', 'ancho' => 16],
                    ['clave' => 'variedad', 'titulo' => 'Variedad', 'ancho' => 18],
                    ['clave' => 'calibre', 'titulo' => 'Calibre', 'ancho' => 13],
                    ['clave' => 'envase', 'titulo' => 'Envase', 'ancho' => 22],
                    ['clave' => 'categoria', 'titulo' => 'Categoría', 'ancho' => 18],
                    ['clave' => 'csg', 'titulo' => 'CSG', 'ancho' => 15],
                    ['clave' => 'predio', 'titulo' => 'Predio', 'ancho' => 20],
                    ['clave' => 'condicion_termica', 'titulo' => 'Condición térmica', 'ancho' => 22],
                    ['clave' => 'habilitacion_almacenamiento', 'titulo' => 'Habilitación almacenamiento', 'ancho' => 28],
                    ['clave' => 'tunel_prefrio', 'titulo' => 'Túnel / proceso Prefrío', 'ancho' => 25],
                    ['clave' => 'camara', 'titulo' => 'Cámara', 'ancho' => 18],
                    ['clave' => 'posicion', 'titulo' => 'Posición', 'ancho' => 18],
                    ['clave' => 'carga', 'titulo' => 'Carga', 'ancho' => 18],
                    ['clave' => 'orden_embarque', 'titulo' => 'Orden de embarque', 'ancho' => 20],
                    ['clave' => 'estado_carga', 'titulo' => 'Estado en carga', 'ancho' => 20],
                    ['clave' => 'anden', 'titulo' => 'Andén', 'ancho' => 18],
                    ['clave' => 'fecha_ingreso', 'titulo' => 'Fecha de ingreso', 'ancho' => 22],
                    ['clave' => 'ultima_actualizacion', 'titulo' => 'Última actualización', 'ancho' => 22],
                ],
            ],
            self::MATERIALES => [
                'tipo' => self::MATERIALES,
                'titulo' => 'Existencia de materiales',
                'descripcion' => 'Saldos de materiales distribuidos entre Bodega y centros de costo, con total empresa.',
                'archivo' => 'Existencia_Materiales',
                'columnas' => [
                    ['clave' => 'temporada', 'titulo' => 'Temporada', 'ancho' => 18],
                    ['clave' => 'folio', 'titulo' => 'Folio material', 'ancho' => 24],
                    ['clave' => 'codigo_item', 'titulo' => 'Código de ítem', 'ancho' => 18],
                    ['clave' => 'item', 'titulo' => 'Ítem', 'ancho' => 35],
                    ['clave' => 'categoria_operacional', 'titulo' => 'Categoría operacional', 'ancho' => 22],
                    ['clave' => 'cliente', 'titulo' => 'Cliente', 'ancho' => 24],
                    ['clave' => 'proveedor', 'titulo' => 'Proveedor', 'ancho' => 24],
                    ['clave' => 'lote', 'titulo' => 'Lote', 'ancho' => 18],
                    ['clave' => 'tipo_almacen', 'titulo' => 'Tipo de almacén', 'ancho' => 18],
                    ['clave' => 'codigo_almacen', 'titulo' => 'Código de almacén', 'ancho' => 20],
                    ['clave' => 'almacen', 'titulo' => 'Almacén / centro de costo', 'ancho' => 30],
                    ['clave' => 'centro_costo', 'titulo' => 'Centro de costo', 'ancho' => 20],
                    ['clave' => 'cantidad_inicial', 'titulo' => 'Cantidad inicial del folio (una vez)', 'ancho' => 31, 'tipo' => 'numero'],
                    ['clave' => 'cantidad_actual', 'titulo' => 'Cantidad actual en almacén', 'ancho' => 24, 'tipo' => 'numero'],
                    ['clave' => 'cantidad_reservada', 'titulo' => 'Cantidad reservada en almacén', 'ancho' => 27, 'tipo' => 'numero'],
                    ['clave' => 'cantidad_disponible', 'titulo' => 'Cantidad disponible en almacén', 'ancho' => 28, 'tipo' => 'numero'],
                    ['clave' => 'cantidad_total_empresa', 'titulo' => 'Cantidad total empresa (una vez por folio)', 'ancho' => 38, 'tipo' => 'numero'],
                    ['clave' => 'unidad_medida', 'titulo' => 'Unidad de medida', 'ancho' => 18],
                    ['clave' => 'estado_operacional', 'titulo' => 'Estado operacional', 'ancho' => 22],
                    ['clave' => 'estado_ubicacion', 'titulo' => 'Estado de ubicación', 'ancho' => 22],
                    ['clave' => 'reservable', 'titulo' => 'Disponible para reserva', 'ancho' => 22],
                    ['clave' => 'motivo_bloqueo', 'titulo' => 'Motivo de bloqueo', 'ancho' => 30],
                    ['clave' => 'camara', 'titulo' => 'Cámara', 'ancho' => 18],
                    ['clave' => 'posicion', 'titulo' => 'Posición', 'ancho' => 18],
                    ['clave' => 'fecha_ingreso', 'titulo' => 'Fecha de ingreso', 'ancho' => 22],
                    ['clave' => 'fecha_fabricacion', 'titulo' => 'Fecha de fabricación', 'ancho' => 20],
                    ['clave' => 'fecha_vencimiento', 'titulo' => 'Fecha de vencimiento', 'ancho' => 20],
                ],
            ],
            self::MATERIA_PRIMA => [
                'tipo' => self::MATERIA_PRIMA,
                'titulo' => 'Existencia de materia prima',
                'descripcion' => 'Lotes vigentes de materia prima, hidrocooler y cámara asignada.',
                'archivo' => 'Existencia_Materia_Prima',
                'columnas' => [
                    ['clave' => 'temporada', 'titulo' => 'Temporada', 'ancho' => 18],
                    ['clave' => 'numero_lote', 'titulo' => 'Número de lote', 'ancho' => 22],
                    ['clave' => 'estado', 'titulo' => 'Estado', 'ancho' => 22],
                    ['clave' => 'numero_recepcion', 'titulo' => 'Número de recepción', 'ancho' => 22],
                    ['clave' => 'guia_despacho', 'titulo' => 'Guía de despacho', 'ancho' => 20],
                    ['clave' => 'cliente', 'titulo' => 'Cliente', 'ancho' => 24],
                    ['clave' => 'csg', 'titulo' => 'CSG', 'ancho' => 15],
                    ['clave' => 'predio', 'titulo' => 'Predio', 'ancho' => 22],
                    ['clave' => 'ggn', 'titulo' => 'GGN', 'ancho' => 18],
                    ['clave' => 'sdp', 'titulo' => 'SdP', 'ancho' => 15],
                    ['clave' => 'fecha_cosecha', 'titulo' => 'Fecha de cosecha', 'ancho' => 18],
                    ['clave' => 'especie', 'titulo' => 'Especie', 'ancho' => 16],
                    ['clave' => 'variedad', 'titulo' => 'Variedad', 'ancho' => 18],
                    ['clave' => 'calibre', 'titulo' => 'Calibre', 'ancho' => 13],
                    ['clave' => 'cuartel', 'titulo' => 'Cuartel', 'ancho' => 16],
                    ['clave' => 'tipo_producto', 'titulo' => 'Tipo de producto', 'ancho' => 20],
                    ['clave' => 'envase_primario', 'titulo' => 'Envase primario', 'ancho' => 18],
                    ['clave' => 'cantidad_primarios', 'titulo' => 'Cantidad envases primarios', 'ancho' => 24, 'tipo' => 'numero'],
                    ['clave' => 'envase_secundario', 'titulo' => 'Envase secundario', 'ancho' => 20],
                    ['clave' => 'cantidad_secundarios', 'titulo' => 'Cantidad envases secundarios', 'ancho' => 26, 'tipo' => 'numero'],
                    ['clave' => 'kilos_brutos', 'titulo' => 'Kilos brutos', 'ancho' => 16, 'tipo' => 'numero'],
                    ['clave' => 'kilos_netos_calculados', 'titulo' => 'Kilos netos calculados', 'ancho' => 22, 'tipo' => 'numero'],
                    ['clave' => 'kilos_netos_confirmados', 'titulo' => 'Kilos netos confirmados', 'ancho' => 23, 'tipo' => 'numero'],
                    ['clave' => 'diferencia_peso', 'titulo' => 'Diferencia de peso', 'ancho' => 19, 'tipo' => 'numero'],
                    ['clave' => 'requiere_hidrocooler', 'titulo' => 'Requiere hidrocooler', 'ancho' => 21],
                    ['clave' => 'estado_hidrocooler', 'titulo' => 'Estado hidrocooler', 'ancho' => 21],
                    ['clave' => 'inicio_hidrocooler', 'titulo' => 'Inicio hidrocooler', 'ancho' => 22],
                    ['clave' => 'termino_hidrocooler', 'titulo' => 'Término hidrocooler', 'ancho' => 22],
                    ['clave' => 'temperatura_hidrocooler', 'titulo' => 'Temperatura hidrocooler °C', 'ancho' => 25, 'tipo' => 'numero'],
                    ['clave' => 'camara', 'titulo' => 'Cámara asignada', 'ancho' => 20],
                    ['clave' => 'asignado_at', 'titulo' => 'Fecha de asignación', 'ancho' => 22],
                    ['clave' => 'confirmado_at', 'titulo' => 'Fecha de confirmación', 'ancho' => 22],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function disponiblesPara(User $usuario): array
    {
        return collect($this->definiciones())
            ->filter(fn (array $definicion, string $tipo): bool => $this->puedeConsultar($usuario, $tipo))
            ->values()
            ->all();
    }

    public function puedeConsultar(User $usuario, string $tipo): bool
    {
        return match ($tipo) {
            self::PRODUCTO_TERMINADO => $usuario->can('consultar-catalogo-cargas'),
            self::MATERIALES => $usuario->can('consultar-despachos-materiales'),
            self::MATERIA_PRIMA => $usuario->can('consultar-materia-prima'),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    public function definicion(string $tipo): array
    {
        return $this->definiciones()[$tipo]
            ?? throw new DomainException('El tipo de existencia solicitado no existe.');
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    public function filas(string $tipo): LazyCollection
    {
        return match ($tipo) {
            self::PRODUCTO_TERMINADO => $this->productoTerminado(),
            self::MATERIALES => $this->materiales(),
            self::MATERIA_PRIMA => $this->materiaPrima(),
            default => throw new DomainException('El tipo de existencia solicitado no existe.'),
        };
    }

    public function temporadaActiva(): ?string
    {
        return Temporada::query()
            ->where('activa', true)
            ->value('codigo');
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    private function productoTerminado(): LazyCollection
    {
        return Folio::query()
            ->with([
                'temporada:id,codigo,nombre,activa',
                'ubicacionActual.posicion.camara',
                'procesosPrefrio.proceso.tunel',
                'asignacionCargaActual.carga',
                'asignacionCargaActual.anden',
            ])
            ->where('activo', true)
            ->whereDoesntHave('material')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->orderBy('numero_folio')
            ->orderBy('id')
            ->lazy(200)
            ->map(function (Folio $folio): array {
                $datos = $folio->datos_externos ?? [];
                $ubicacion = $folio->ubicacionActual;
                $posicion = $ubicacion?->posicion;
                $asignacionPrefrioActiva = $folio->procesosPrefrio
                    ->filter(fn ($asignacion): bool => $asignacion->proceso?->estado?->esActivo() === true
                        && ! in_array($asignacion->estado, [
                            EstadoFolioProcesoPrefrio::Retirado,
                            EstadoFolioProcesoPrefrio::Cancelado,
                        ], true))
                    ->sortByDesc(fn ($asignacion) => $asignacion->cargado_at?->getTimestamp() ?? 0)
                    ->first();
                $proceso = $asignacionPrefrioActiva?->proceso;
                $pendienteUbicacion = $ubicacion === null
                    && $asignacionPrefrioActiva === null
                    && $folio->condicion_termica === CondicionTermicaFolio::PrefrioAprobado
                    && $folio->habilitacion_almacenamiento === HabilitacionAlmacenamientoFolio::Habilitado;
                $asignacionCarga = $folio->asignacionCargaActual;
                $carga = $asignacionCarga?->carga;

                return [
                    'temporada' => $folio->temporada?->codigo,
                    'folio' => $folio->numero_folio,
                    'tipo_bulto' => $this->humanizar($folio->tipo_bulto->value),
                    'estado_operacional' => $pendienteUbicacion
                        ? 'Pendiente de ubicación'
                        : $this->humanizar($folio->estado_operacional->value),
                    'etapa_actual' => $this->etapaProducto(
                        $folio,
                        $ubicacion !== null,
                        $asignacionPrefrioActiva !== null,
                        $asignacionCarga?->estado?->value,
                    ),
                    'cantidad_cajas' => isset($datos['cantidad_cajas']) ? (int) $datos['cantidad_cajas'] : null,
                    'cliente' => $folio->exportadora,
                    'marca' => $folio->marca,
                    'especie' => $datos['especie'] ?? null,
                    'variedad' => $folio->variedad,
                    'calibre' => $folio->calibre,
                    'envase' => $datos['envase'] ?? null,
                    'categoria' => $datos['categoria'] ?? null,
                    'csg' => $datos['csg'] ?? null,
                    'predio' => $datos['predio'] ?? null,
                    'condicion_termica' => $this->humanizar($folio->condicion_termica->value),
                    'habilitacion_almacenamiento' => $this->humanizar($folio->habilitacion_almacenamiento->value),
                    'tunel_prefrio' => $proceso
                        ? trim(($proceso->tunel?->codigo ?? '').' · '.$proceso->codigo, ' ·')
                        : null,
                    'camara' => $posicion?->camara
                        ? trim($posicion->camara->codigo.' · '.$posicion->camara->nombre)
                        : null,
                    'posicion' => $posicion?->etiqueta,
                    'carga' => $carga?->codigo,
                    'orden_embarque' => $carga?->numero_orden_externa,
                    'estado_carga' => $this->humanizar($asignacionCarga?->estado?->value),
                    'anden' => $asignacionCarga?->anden
                        ? trim($asignacionCarga->anden->codigo.' · '.$asignacionCarga->anden->nombre)
                        : null,
                    'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                    'ultima_actualizacion' => collect([
                        $folio->updated_at,
                        $ubicacion?->ubicado_at,
                        $asignacionPrefrioActiva?->updated_at,
                        $asignacionCarga?->updated_at,
                        $asignacionCarga?->enviado_anden_at,
                    ])->filter()->sortDesc()->first()?->toAtomString(),
                ];
            });
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    private function materiales(): LazyCollection
    {
        $folioAnterior = null;

        return SaldoMaterialAlmacen::query()
            ->with([
                'folioMaterial.folio',
                'folioMaterial.item.cliente.temporada',
                'folioMaterial.item.cliente.cliente',
                'folioMaterial.proveedorMaterial',
                'almacen',
                'camara',
                'posicion',
            ])
            ->where('cantidad_actual', '>', 0)
            ->whereHas('folioMaterial.folio', fn ($consulta) => $consulta->where('activo', true))
            ->whereHas(
                'folioMaterial.item.cliente.temporada',
                fn ($consulta) => $consulta->where('activa', true),
            )
            ->orderBy('folio_id')
            ->orderBy('almacen_material_id')
            ->lazy(200)
            ->map(function (SaldoMaterialAlmacen $saldo) use (&$folioAnterior): array {
                $material = $saldo->folioMaterial;
                $folio = $material->folio;
                $almacen = $saldo->almacen;
                $posicion = $saldo->posicion;
                $camara = $saldo->camara ?? $posicion?->camara;
                $almacenFisico = $almacen?->requiere_ubicacion_fisica === true;
                $ubicacionValida = ! $almacenFisico
                    || ($camara?->contenido === ContenidoCamara::Materiales
                        && (! $posicion || $posicion->estado === EstadoPosicion::Activa)
                        && $camara->estado === EstadoCamara::Activa);
                $actual = (float) $saldo->cantidad_actual;
                $reservada = (float) $saldo->cantidad_reservada;
                $operable = $almacen?->activo === true
                    && $ubicacionValida
                    && $folio->estado_operacional === EstadoOperacionalFolio::Disponible
                    && $material->motivo_bloqueo === null;
                $disponible = $operable ? max(0, $actual - $reservada) : 0;
                $reservable = $operable
                    && $almacen?->codigo === AlmacenMaterial::CODIGO_BODEGA_CENTRAL
                    && $disponible > 0.0001;
                $primeraFilaFolio = $folioAnterior !== $folio->id;
                $folioAnterior = $folio->id;

                return [
                    'temporada' => $material->item->cliente->temporada?->codigo,
                    'folio' => $folio->numero_folio,
                    'codigo_item' => $material->item->codigo,
                    'item' => $material->item->nombre,
                    'categoria_operacional' => $this->humanizar($material->categoria_operacional?->value),
                    'cliente' => $material->item->cliente->nombre,
                    'proveedor' => $material->proveedor ?? $material->proveedorMaterial?->nombre,
                    'lote' => $material->lote,
                    'tipo_almacen' => $almacen?->tipo
                        ? $this->humanizar($almacen->tipo->value)
                        : null,
                    'codigo_almacen' => $almacen?->codigo,
                    'almacen' => $almacen?->nombre,
                    'centro_costo' => $almacen?->centro_costo,
                    'cantidad_inicial' => $primeraFilaFolio
                        ? (float) $material->cantidad_inicial
                        : null,
                    'cantidad_actual' => $actual,
                    'cantidad_reservada' => $reservada,
                    'cantidad_disponible' => $disponible,
                    'cantidad_total_empresa' => $primeraFilaFolio
                        ? (float) $material->cantidad_actual
                        : null,
                    'unidad_medida' => $material->unidad_medida,
                    'estado_operacional' => $this->humanizar($folio->estado_operacional->value),
                    'estado_ubicacion' => ! $almacenFisico
                        ? 'Almacén virtual'
                        : (! $camara
                            ? 'Pendiente de ubicación'
                            : ($posicion ? 'Ubicado' : 'Solo en cámara')),
                    'reservable' => $reservable ? 'Sí' : 'No',
                    'motivo_bloqueo' => $material->motivo_bloqueo,
                    'camara' => $camara ? trim($camara->codigo.' · '.$camara->nombre) : null,
                    'posicion' => $posicion?->etiqueta,
                    'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                    'fecha_fabricacion' => $material->fecha_fabricacion?->toDateString(),
                    'fecha_vencimiento' => $material->fecha_vencimiento?->toDateString(),
                ];
            });
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    private function materiaPrima(): LazyCollection
    {
        return LoteMateriaPrima::query()
            ->with([
                'temporada:id,codigo,nombre,activa',
                'cliente:id,codigo,nombre',
                'recepcion:id,numero_recepcion,numero_guia_despacho',
                'hidrocooler',
                'asignacionCamara.camara:id,codigo,nombre',
            ])
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->where('estado', '!=', 'anulado')
            ->orderBy('numero_lote')
            ->orderBy('id')
            ->lazy(200)
            ->map(function (LoteMateriaPrima $lote): array {
                $hidrocooler = $lote->hidrocooler;
                $asignacion = $lote->asignacionCamara;
                $camara = $asignacion?->camara;
                $calculados = (float) $lote->kilos_netos_calculados;
                $confirmados = (float) $lote->kilos_netos_confirmados;

                return [
                    'temporada' => $lote->temporada?->codigo,
                    'numero_lote' => $lote->numero_lote,
                    'estado' => $this->humanizar($lote->estado->value),
                    'numero_recepcion' => $lote->recepcion?->numero_recepcion,
                    'guia_despacho' => $lote->recepcion?->numero_guia_despacho,
                    'cliente' => $lote->cliente?->nombre,
                    'csg' => $lote->csg_snapshot,
                    'predio' => $lote->predio,
                    'ggn' => $lote->ggn,
                    'sdp' => $lote->sdp,
                    'fecha_cosecha' => $lote->fecha_cosecha?->toDateString(),
                    'especie' => $lote->especie_snapshot,
                    'variedad' => $lote->variedad_snapshot,
                    'calibre' => $lote->calibre_snapshot,
                    'cuartel' => $lote->cuartel,
                    'tipo_producto' => $this->humanizar($lote->tipo_producto->value),
                    'envase_primario' => $this->humanizar($lote->envase_primario->value),
                    'cantidad_primarios' => $lote->cantidad_envases_primarios,
                    'envase_secundario' => $this->humanizar($lote->envase_secundario?->value),
                    'cantidad_secundarios' => $lote->cantidad_envases_secundarios,
                    'kilos_brutos' => (float) $lote->kilos_brutos,
                    'kilos_netos_calculados' => $calculados,
                    'kilos_netos_confirmados' => $confirmados,
                    'diferencia_peso' => $confirmados - $calculados,
                    'requiere_hidrocooler' => $lote->requiere_hidrocooler ? 'Sí' : 'No',
                    'estado_hidrocooler' => $this->humanizar($hidrocooler?->estado?->value),
                    'inicio_hidrocooler' => $hidrocooler?->inicio_at?->toAtomString(),
                    'termino_hidrocooler' => $hidrocooler?->termino_at?->toAtomString(),
                    'temperatura_hidrocooler' => $hidrocooler?->temperatura_c !== null
                        ? (float) $hidrocooler->temperatura_c
                        : null,
                    'camara' => $camara ? trim($camara->codigo.' · '.$camara->nombre) : null,
                    'asignado_at' => $asignacion?->asignado_at?->toAtomString(),
                    'confirmado_at' => $lote->confirmado_at?->toAtomString(),
                ];
            });
    }

    private function etapaProducto(
        Folio $folio,
        bool $ubicado,
        bool $enPrefrio,
        ?string $estadoCarga,
    ): string {
        if ($estadoCarga === 'en_anden') {
            return 'En andén';
        }

        if ($estadoCarga === 'con_incidencia') {
            return 'Carga con incidencia';
        }

        if ($estadoCarga === 'pendiente') {
            return 'Reservado para carga';
        }

        if ($ubicado) {
            return 'Ubicado en cámara';
        }

        if ($enPrefrio) {
            return 'En Prefrío';
        }

        if ($folio->condicion_termica === CondicionTermicaFolio::PrefrioAprobado
            && $folio->habilitacion_almacenamiento === HabilitacionAlmacenamientoFolio::Habilitado) {
            return 'Pendiente de ubicación';
        }

        return match ($folio->condicion_termica->value) {
            'pendiente_prefrio' => 'Pendiente de Prefrío',
            'requiere_reproceso' => 'Requiere reproceso',
            'retenido' => 'Retenido',
            default => $this->humanizar($folio->estado_operacional->value),
        };
    }

    private function humanizar(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $valor));
    }
}
