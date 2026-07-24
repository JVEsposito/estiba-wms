<?php

namespace App\Services\Materiales;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\ContenidoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoOrdenTransformacionMaterial;
use App\Enums\EstadoReservaMaterial;
use App\Enums\EstadoVersionRecetaMaterial;
use App\Enums\TipoEventoTransformacionMaterial;
use App\Exceptions\ConflictoOperacion;
use App\Models\Cliente;
use App\Models\DetalleVersionRecetaMaterial;
use App\Models\EventoTransformacionMaterial;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\OrdenTransformacionMaterial;
use App\Models\RecetaMaterial;
use App\Models\ReservaTransformacionMaterial;
use App\Models\User;
use App\Models\VersionRecetaMaterial;
use App\Services\Temporadas\ServicioTemporadaActiva;
use BackedEnum;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use JsonException;

class ServicioTransformacionMaterial
{
    public function __construct(
        private readonly ServicioTemporadaActiva $temporadaActiva,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearReceta(array $datos, User $usuario): RecetaMaterial
    {
        return DB::transaction(function () use ($datos, $usuario): RecetaMaterial {
            $temporada = $this->temporadaActiva->obtener(bloquear: true);
            $cliente = Cliente::query()
                ->whereKey($datos['cliente_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $salida = $this->validarItem(
                $datos['item_salida_id'],
                $cliente,
                $temporada->id,
                [CategoriaOperacionalMaterial::MaterialPt],
                bloquear: true,
            );
            $componentes = collect($datos['componentes']);

            if ($componentes->where('es_componente_principal', true)->count() !== 1) {
                throw new DomainException('La receta debe tener exactamente un componente principal.');
            }

            $receta = RecetaMaterial::create([
                'temporada_id' => $temporada->id,
                'cliente_id' => $cliente->id,
                'item_salida_id' => $salida->id,
                'nombre' => trim($datos['nombre']),
                'activa' => true,
                'creado_por_user_id' => $usuario->id,
                'actualizado_por_user_id' => $usuario->id,
            ]);
            $version = VersionRecetaMaterial::create([
                'receta_material_id' => $receta->id,
                'numero_version' => 1,
                'estado' => EstadoVersionRecetaMaterial::Activa,
                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),
                'unidad_medida_salida' => $salida->unidad_medida,
                'creado_por_user_id' => $usuario->id,
                'activado_at' => now(),
            ]);
            $detallesSnapshot = [];

            foreach ($componentes as $componente) {
                $item = $this->validarItem(
                    $componente['item_entrada_id'],
                    $cliente,
                    $temporada->id,
                    [
                        CategoriaOperacionalMaterial::Insumo,
                        CategoriaOperacionalMaterial::MaterialMp,
                    ],
                    bloquear: true,
                );

                if ($item->id === $salida->id) {
                    throw new DomainException('El ítem de salida no puede utilizarse como entrada de la misma receta.');
                }

                $detalle = DetalleVersionRecetaMaterial::create([
                    'version_receta_material_id' => $version->id,
                    'item_entrada_id' => $item->id,
                    'cantidad_estandar' => $this->cantidad($componente['cantidad_estandar']),
                    'unidad_medida' => $item->unidad_medida,
                    'es_componente_principal' => (bool) $componente['es_componente_principal'],
                    'factor_conversion' => $this->cantidad($componente['factor_conversion'] ?? 1),
                    'merma_estandar_porcentaje' => $this->porcentaje(
                        $componente['merma_estandar_porcentaje'] ?? 0,
                    ),
                    'tolerancia_porcentaje' => $this->porcentaje(
                        $componente['tolerancia_porcentaje'] ?? 0,
                    ),
                ]);
                $detallesSnapshot[] = [
                    'id' => $detalle->id,
                    'item_id' => $item->id,
                    'codigo' => $item->codigo,
                    'nombre' => $item->nombre,
                    'categoria_operacional' => $item->categoria_operacional->value,
                    'cantidad_estandar' => $detalle->cantidad_estandar,
                    'unidad_medida' => $detalle->unidad_medida,
                    'es_componente_principal' => $detalle->es_componente_principal,
                    'factor_conversion' => $detalle->factor_conversion,
                    'merma_estandar_porcentaje' => $detalle->merma_estandar_porcentaje,
                    'tolerancia_porcentaje' => $detalle->tolerancia_porcentaje,
                ];
            }

            $version->update(['snapshot' => [
                'receta' => [
                    'id' => $receta->id,
                    'nombre' => $receta->nombre,
                ],
                'cliente' => [
                    'id' => $cliente->id,
                    'codigo' => $cliente->codigo,
                    'nombre' => $cliente->nombre,
                ],
                'salida' => [
                    'item_id' => $salida->id,
                    'codigo' => $salida->codigo,
                    'nombre' => $salida->nombre,
                    'cantidad_base' => $version->cantidad_base_salida,
                    'unidad_medida' => $salida->unidad_medida,
                ],
                'componentes' => $detallesSnapshot,
            ]]);

            return $this->cargarReceta($receta->refresh());
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearOrden(array $datos, User $usuario): OrdenTransformacionMaterial
    {
        $payloadHash = $this->payloadHash($datos);

        return DB::transaction(function () use ($datos, $usuario, $payloadHash): OrdenTransformacionMaterial {
            $existente = OrdenTransformacionMaterial::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->creado_por_user_id !== $usuario->id
                    || ! hash_equals($existente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El UUID de creación de la orden ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargarOrden($existente);
            }

            $temporada = $this->temporadaActiva->obtener(bloquear: true);
            $version = VersionRecetaMaterial::query()
                ->with(['receta.itemSalida', 'receta.cliente', 'detalles.itemEntrada'])
                ->whereKey($datos['version_receta_material_id'])
                ->where('estado', EstadoVersionRecetaMaterial::Activa->value)
                ->lockForUpdate()
                ->firstOrFail();
            $receta = $version->receta;

            if (! $receta->activa || $receta->temporada_id !== $temporada->id) {
                throw new DomainException('La receta no pertenece a la temporada global activa.');
            }

            $orden = OrdenTransformacionMaterial::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $payloadHash,
                'temporada_id' => $temporada->id,
                'cliente_id' => $receta->cliente_id,
                'version_receta_material_id' => $version->id,
                'estado' => EstadoOrdenTransformacionMaterial::Borrador,
                'cantidad_planificada_salida' => $this->cantidad($datos['cantidad_planificada_salida']),
                'linea' => $this->textoOpcional($datos['linea'] ?? null),
                'turno' => $this->textoOpcional($datos['turno'] ?? null),
                'fecha_operacional' => $datos['fecha_operacional'],
                'version' => 1,
                'snapshot_receta' => $version->snapshot,
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null),
                'creado_por_user_id' => $usuario->id,
            ]);
            $this->registrarEvento(
                $orden,
                TipoEventoTransformacionMaterial::Creada,
                $usuario,
                $datos['operacion_id'],
                ['cantidad_planificada_salida' => $orden->cantidad_planificada_salida],
            );

            return $this->cargarOrden($orden->refresh());
        }, attempts: 3);
    }

    public function planificar(
        OrdenTransformacionMaterial $orden,
        string $operacionId,
        int $versionConocida,
        User $usuario,
    ): OrdenTransformacionMaterial {
        return DB::transaction(function () use (
            $orden,
            $operacionId,
            $versionConocida,
            $usuario,
        ): OrdenTransformacionMaterial {
            $eventoExistente = EventoTransformacionMaterial::query()
                ->where('operacion_id', $operacionId)
                ->lockForUpdate()
                ->first();

            if ($eventoExistente) {
                if ($eventoExistente->orden_transformacion_material_id !== $orden->id
                    || $eventoExistente->tipo !== TipoEventoTransformacionMaterial::Planificada
                    || $eventoExistente->user_id !== $usuario->id
                    || (int) data_get($eventoExistente->datos, 'version_conocida') !== $versionConocida) {
                    throw new ConflictoOperacion(
                        'El UUID de planificación ya fue utilizado por otra operación.',
                    );
                }

                return $this->cargarOrden($orden->refresh());
            }

            $orden = OrdenTransformacionMaterial::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if ($orden->estado !== EstadoOrdenTransformacionMaterial::Borrador) {
                throw new DomainException('La orden ya no se encuentra en borrador.');
            }

            if ($orden->version !== $versionConocida) {
                throw new ConflictoOperacion('La orden cambió desde la última lectura.');
            }

            $snapshot = $orden->snapshot_receta;
            $cantidadBaseSalida = round((float) data_get($snapshot, 'salida.cantidad_base'), 3);
            $componentes = data_get($snapshot, 'componentes');

            if ($cantidadBaseSalida <= 0 || ! is_array($componentes) || $componentes === []) {
                throw new DomainException('El snapshot de receta de la orden no es válido.');
            }

            $requerimientos = [];

            foreach ($componentes as $componente) {
                $itemId = (string) ($componente['item_id'] ?? '');
                $codigo = trim((string) ($componente['codigo'] ?? ''));
                $unidadMedida = trim((string) ($componente['unidad_medida'] ?? ''));
                $cantidadEstandar = round((float) ($componente['cantidad_estandar'] ?? 0), 3);

                if ($itemId === '' || $codigo === '' || $unidadMedida === '' || $cantidadEstandar <= 0) {
                    throw new DomainException('El snapshot de receta contiene un componente inválido.');
                }

                $requerido = round(
                    $cantidadEstandar
                    * (float) $orden->cantidad_planificada_salida
                    / $cantidadBaseSalida,
                    3,
                );
                $pendiente = $requerido;
                $ordenFifo = 1;
                $folios = FolioMaterial::query()
                    ->join('folios', 'folios.id', '=', 'folios_materiales.folio_id')
                    ->select('folios_materiales.*')
                    ->where('folios_materiales.item_material_id', $itemId)
                    ->whereNull('folios_materiales.motivo_bloqueo')
                    ->where('folios.activo', true)
                    ->where('folios.estado_operacional', EstadoOperacionalFolio::Disponible->value)
                    ->whereHas('folio.ubicacionActual.posicion.camara', fn ($consulta) => $consulta
                        ->where('contenido', ContenidoCamara::Materiales->value))
                    ->orderBy('folios.fecha_ingreso')
                    ->orderBy('folios.numero_folio')
                    ->lockForUpdate()
                    ->get();

                foreach ($folios as $folio) {
                    $disponible = round(
                        (float) $folio->cantidad_actual - (float) $folio->cantidad_reservada,
                        3,
                    );

                    if ($disponible <= 0) {
                        continue;
                    }

                    $cantidad = min($pendiente, $disponible);
                    ReservaTransformacionMaterial::create([
                        'orden_transformacion_material_id' => $orden->id,
                        'folio_id' => $folio->folio_id,
                        'item_material_id' => $itemId,
                        'cantidad' => $cantidad,
                        'estado' => EstadoReservaMaterial::Activa,
                        'orden_fifo' => $ordenFifo++,
                    ]);
                    $folio->increment('cantidad_reservada', $cantidad);
                    $pendiente = round($pendiente - $cantidad, 3);

                    if ($pendiente <= 0) {
                        break;
                    }
                }

                if ($pendiente > 0.0001) {
                    throw new DomainException(sprintf(
                        'No existe saldo disponible suficiente para el ítem %s. Faltan %.3f %s.',
                        $codigo,
                        $pendiente,
                        $unidadMedida,
                    ));
                }

                $requerimientos[] = [
                    'item_material_id' => $itemId,
                    'codigo' => $codigo,
                    'cantidad_requerida' => number_format($requerido, 3, '.', ''),
                    'unidad_medida' => $unidadMedida,
                ];
            }

            $orden->update([
                'estado' => EstadoOrdenTransformacionMaterial::Planificada,
                'version' => $orden->version + 1,
            ]);
            $this->registrarEvento(
                $orden,
                TipoEventoTransformacionMaterial::Planificada,
                $usuario,
                $operacionId,
                [
                    'version_conocida' => $versionConocida,
                    'requerimientos' => $requerimientos,
                ],
            );

            return $this->cargarOrden($orden->refresh());
        }, attempts: 3);
    }

    public function cancelar(
        OrdenTransformacionMaterial $orden,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): OrdenTransformacionMaterial {
        $motivo = trim($motivo);

        return DB::transaction(function () use ($orden, $operacionId, $motivo, $usuario): OrdenTransformacionMaterial {
            $eventoExistente = EventoTransformacionMaterial::query()
                ->where('operacion_id', $operacionId)
                ->lockForUpdate()
                ->first();

            if ($eventoExistente) {
                if ($eventoExistente->orden_transformacion_material_id !== $orden->id
                    || $eventoExistente->tipo !== TipoEventoTransformacionMaterial::Cancelada
                    || $eventoExistente->user_id !== $usuario->id
                    || $eventoExistente->observacion !== $motivo) {
                    throw new ConflictoOperacion(
                        'El UUID de cancelación ya fue utilizado por otra operación.',
                    );
                }

                return $this->cargarOrden($orden->refresh());
            }

            $orden = OrdenTransformacionMaterial::query()
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (! in_array($orden->estado, [
                EstadoOrdenTransformacionMaterial::Borrador,
                EstadoOrdenTransformacionMaterial::Planificada,
            ], true)) {
                throw new DomainException('La orden ya no puede cancelarse sin movimientos compensatorios.');
            }

            $reservas = ReservaTransformacionMaterial::query()
                ->where('orden_transformacion_material_id', $orden->id)
                ->where('estado', EstadoReservaMaterial::Activa->value)
                ->lockForUpdate()
                ->get();

            foreach ($reservas as $reserva) {
                $folio = FolioMaterial::query()->lockForUpdate()->findOrFail($reserva->folio_id);
                $folio->update([
                    'cantidad_reservada' => max(
                        0,
                        round((float) $folio->cantidad_reservada - (float) $reserva->cantidad, 3),
                    ),
                ]);
                $reserva->update(['estado' => EstadoReservaMaterial::Liberada]);
            }

            $orden->update([
                'estado' => EstadoOrdenTransformacionMaterial::Cancelada,
                'version' => $orden->version + 1,
                'cancelado_por_user_id' => $usuario->id,
                'cancelado_at' => now(),
                'motivo_cancelacion' => $motivo,
            ]);
            $this->registrarEvento(
                $orden,
                TipoEventoTransformacionMaterial::Cancelada,
                $usuario,
                $operacionId,
                ['reservas_liberadas' => $reservas->count()],
                $motivo,
            );

            return $this->cargarOrden($orden->refresh());
        }, attempts: 3);
    }

    public function cargarReceta(RecetaMaterial $receta): RecetaMaterial
    {
        return $receta->load([
            'temporada:id,codigo,nombre,activa',
            'cliente:id,codigo,nombre,codigo_folio_materiales,activo',
            'itemSalida',
            'versiones' => fn ($consulta) => $consulta->orderByDesc('numero_version'),
            'versiones.detalles.itemEntrada',
            'creadoPor:id,name',
            'actualizadoPor:id,name',
        ]);
    }

    public function cargarOrden(OrdenTransformacionMaterial $orden): OrdenTransformacionMaterial
    {
        return $orden->load([
            'temporada:id,codigo,nombre,activa',
            'cliente:id,codigo,nombre,codigo_folio_materiales,activo',
            'versionReceta.receta.itemSalida',
            'versionReceta.detalles.itemEntrada',
            'reservas' => fn ($consulta) => $consulta->orderBy('item_material_id')->orderBy('orden_fifo'),
            'reservas.folioMaterial.folio.ubicacionActual.posicion.camara',
            'eventos' => fn ($consulta) => $consulta->orderBy('ocurrido_at'),
            'eventos.usuario:id,name',
            'creadoPor:id,name',
        ]);
    }

    /**
     * @param  array<int, CategoriaOperacionalMaterial>  $categorias
     */
    private function validarItem(
        string $itemId,
        Cliente $cliente,
        string $temporadaId,
        array $categorias,
        bool $bloquear = false,
    ): ItemMaterial {
        $consulta = ItemMaterial::query()
            ->with(['cliente.cliente', 'cliente.temporada'])
            ->whereKey($itemId)
            ->where('activo', true);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $item = $consulta->first();

        if (! $item
            || ! $item->cliente?->activo
            || ! $item->cliente?->cliente?->activo
            || $item->cliente->cliente_id !== $cliente->id
            || $item->cliente->temporada?->temporada_id !== $temporadaId
            || ! $item->cliente->temporada?->activa) {
            throw new DomainException('Uno de los ítems no pertenece al cliente y temporada activos.');
        }

        if (! in_array($item->categoria_operacional, $categorias, true)) {
            throw new DomainException(sprintf(
                'El ítem %s no posee una categoría operacional válida para esta receta.',
                $item->codigo,
            ));
        }

        return $item;
    }

    private function registrarEvento(
        OrdenTransformacionMaterial $orden,
        TipoEventoTransformacionMaterial $tipo,
        User $usuario,
        ?string $operacionId,
        ?array $datos = null,
        ?string $observacion = null,
    ): void {
        EventoTransformacionMaterial::create([
            'orden_transformacion_material_id' => $orden->id,
            'operacion_id' => $operacionId,
            'tipo' => $tipo,
            'datos' => $datos,
            'observacion' => $observacion,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
        ]);
    }

    private function cantidad(mixed $valor): float
    {
        $cantidad = round((float) $valor, 3);

        if ($cantidad <= 0) {
            throw new DomainException('Las cantidades deben ser mayores que cero.');
        }

        return $cantidad;
    }

    private function porcentaje(mixed $valor): float
    {
        $porcentaje = round((float) $valor, 4);

        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new DomainException('Los porcentajes deben encontrarse entre 0 y 100.');
        }

        return $porcentaje;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function payloadHash(array $datos): string
    {
        try {
            return hash('sha256', json_encode(
                $this->normalizar($datos),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));
        } catch (JsonException $exception) {
            throw new DomainException('No fue posible normalizar la operación.', previous: $exception);
        }
    }

    private function normalizar(mixed $valor): mixed
    {
        if ($valor instanceof BackedEnum) {
            return $valor->value;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format(DATE_ATOM);
        }

        if (! is_array($valor)) {
            return $valor;
        }

        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(fn (mixed $item): mixed => $this->normalizar($item), $valor);
    }
}
