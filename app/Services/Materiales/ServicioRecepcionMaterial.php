<?php

namespace App\Services\Materiales;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoRecepcionMaterial;
use App\Enums\EstadoReservaMaterial;
use App\Enums\TipoBulto;
use App\Enums\TipoEventoRecepcionMaterial;
use App\Enums\TipoMovimientoInventarioMaterial;
use App\Exceptions\ConflictoOperacion;
use App\Models\BultoRecepcionMaterial;
use App\Models\Cliente;
use App\Models\CorrelativoMaterialCliente;
use App\Models\DetalleRecepcionMaterial;
use App\Models\EventoRecepcionMaterial;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\ProveedorMaterial;
use App\Models\RecepcionMaterial;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaActiva;
use BackedEnum;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use JsonException;

class ServicioRecepcionMaterial
{
    private const MAXIMO_CORRELATIVO = 9_999_999;

    public function __construct(
        private readonly ServicioTemporadaActiva $temporadaActiva,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $usuario): RecepcionMaterial
    {
        $payloadHash = $this->payloadHash($datos);

        try {
            return DB::transaction(function () use ($datos, $usuario, $payloadHash): RecepcionMaterial {
                $existente = RecepcionMaterial::query()
                    ->where('operacion_id', $datos['operacion_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    if ($existente->creado_por_user_id !== $usuario->id
                        || ! hash_equals($existente->payload_hash, $payloadHash)) {
                        throw new ConflictoOperacion(
                            'El UUID de creación ya fue utilizado con datos diferentes.',
                        );
                    }

                    return $this->cargar($existente);
                }

                $temporada = $this->temporadaActiva->obtener(bloquear: true);
                [$cliente, $proveedor] = $this->validarCabecera(
                    $datos['cliente_id'],
                    $datos['proveedor_material_id'],
                );
                $recepcion = RecepcionMaterial::create([
                    'operacion_id' => $datos['operacion_id'],
                    'payload_hash' => $payloadHash,
                    'temporada_id' => $temporada->id,
                    'cliente_id' => $cliente->id,
                    'proveedor_material_id' => $proveedor->id,
                    'numero_guia_despacho' => trim($datos['numero_guia_despacho']),
                    'fecha_documento' => $datos['fecha_documento'] ?? null,
                    'orden_compra' => $this->textoOpcional($datos['orden_compra'] ?? null),
                    'patente' => $this->textoOpcional($datos['patente'] ?? null),
                    'transportista' => $this->textoOpcional($datos['transportista'] ?? null),
                    'estado' => EstadoRecepcionMaterial::Borrador,
                    'version' => 1,
                    'observacion' => $this->textoOpcional($datos['observacion'] ?? null),
                    'creado_por_user_id' => $usuario->id,
                ]);

                foreach ($datos['detalles'] as $linea) {
                    $this->crearDetalle($recepcion, $linea, $cliente, $temporada->id);
                }

                $this->registrarEvento(
                    $recepcion,
                    TipoEventoRecepcionMaterial::Creada,
                    $usuario,
                    $datos['operacion_id'],
                    ['numero_guia_despacho' => $recepcion->numero_guia_despacho],
                );

                return $this->cargar($recepcion);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existente = RecepcionMaterial::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();

            if ($existente
                && $existente->creado_por_user_id === $usuario->id
                && hash_equals($existente->payload_hash, $payloadHash)) {
                return $this->cargar($existente);
            }

            throw new ConflictoOperacion(
                'La recepción entró en conflicto con otra operación concurrente.',
                previous: $exception,
            );
        }
    }

    public function confirmar(
        RecepcionMaterial $recepcion,
        string $operacionId,
        int $versionConocida,
        User $usuario,
    ): RecepcionMaterial {
        $payload = [
            'recepcion_material_id' => $recepcion->id,
            'version_conocida' => $versionConocida,
        ];
        $payloadHash = $this->payloadHash($payload);

        return DB::transaction(function () use (
            $recepcion,
            $operacionId,
            $versionConocida,
            $usuario,
            $payloadHash,
        ): RecepcionMaterial {
            $recepcion = RecepcionMaterial::query()
                ->with(['detalles.bultos', 'cliente', 'proveedor'])
                ->lockForUpdate()
                ->findOrFail($recepcion->id);

            if ($recepcion->confirmacion_operacion_id !== null) {
                $mismaOperacion = $recepcion->confirmacion_operacion_id === $operacionId
                    && $recepcion->confirmado_por_user_id === $usuario->id
                    && hash_equals((string) $recepcion->confirmacion_payload_hash, $payloadHash);

                if (! $mismaOperacion) {
                    throw new ConflictoOperacion(
                        'La recepción ya fue confirmada con una operación diferente.',
                    );
                }

                return $this->cargar($recepcion);
            }

            if ($recepcion->estado !== EstadoRecepcionMaterial::Borrador) {
                throw new DomainException('La recepción ya no se encuentra en borrador.');
            }

            if ($recepcion->version !== $versionConocida) {
                throw new ConflictoOperacion('La recepción cambió desde la última lectura.');
            }

            $temporada = $this->temporadaActiva->obtener(bloquear: true);

            if ($temporada->id !== $recepcion->temporada_id) {
                throw new DomainException(
                    'La temporada de la recepción ya no es la temporada global activa.',
                );
            }

            [$cliente, $proveedor] = $this->validarCabecera(
                $recepcion->cliente_id,
                $recepcion->proveedor_material_id,
                exigirCodigoFolio: true,
            );

            if ($recepcion->detalles->isEmpty()) {
                throw new DomainException('La recepción no contiene detalles para confirmar.');
            }

            $foliosGenerados = [];
            $ahora = now();

            foreach ($recepcion->detalles as $detalle) {
                $item = $this->validarItem(
                    $detalle->item_material_id,
                    $cliente,
                    $recepcion->temporada_id,
                    bloquear: true,
                );
                $sumaBultos = round($detalle->bultos->sum(
                    fn (BultoRecepcionMaterial $bulto): float => (float) $bulto->cantidad,
                ), 3);

                if (abs($sumaBultos - (float) $detalle->cantidad_recibida) > 0.0001) {
                    throw new DomainException(sprintf(
                        'La distribución física del ítem %s no coincide con la cantidad recibida.',
                        $item->codigo,
                    ));
                }

                if ($detalle->bultos->isEmpty()) {
                    throw new DomainException(sprintf(
                        'El ítem %s no posee bultos físicos definidos.',
                        $item->codigo,
                    ));
                }

                foreach ($detalle->bultos as $bulto) {
                    $codigoFolio = $this->siguienteFolio($cliente);
                    $folio = Folio::create([
                        'temporada_id' => $recepcion->temporada_id,
                        'numero_folio' => $codigoFolio,
                        'tipo_bulto' => TipoBulto::Material,
                        'estado_operacional' => $bulto->bloqueado
                            ? EstadoOperacionalFolio::Bloqueado
                            : EstadoOperacionalFolio::PendienteUbicacion,
                        'fecha_ingreso' => $ahora,
                        'activo' => true,
                        'origen_sistema' => 'recepcion_materiales',
                        'identificador_externo' => $recepcion->numero_guia_despacho,
                        'datos_externos' => [
                            'recepcion_material_id' => $recepcion->id,
                            'guia_despacho' => $recepcion->numero_guia_despacho,
                            'cliente' => [
                                'id' => $cliente->id,
                                'codigo' => $cliente->codigo,
                                'nombre' => $cliente->nombre,
                            ],
                            'proveedor' => [
                                'id' => $proveedor->id,
                                'codigo' => $proveedor->codigo,
                                'nombre' => $proveedor->nombre,
                            ],
                        ],
                    ]);
                    $material = FolioMaterial::create([
                        'folio_id' => $folio->id,
                        'item_material_id' => $item->id,
                        'bulto_recepcion_material_id' => $bulto->id,
                        'proveedor_material_id' => $proveedor->id,
                        'categoria_operacional' => $detalle->categoria_operacional,
                        'cantidad_inicial' => $bulto->cantidad,
                        'cantidad_actual' => $bulto->cantidad,
                        'cantidad_reservada' => 0,
                        'unidad_medida' => $detalle->unidad_medida,
                        'lote' => $bulto->lote_proveedor,
                        'fecha_fabricacion' => $bulto->fecha_fabricacion,
                        'fecha_vencimiento' => $bulto->fecha_vencimiento,
                        'proveedor' => $proveedor->nombre,
                        'observacion' => $detalle->observacion,
                        'motivo_bloqueo' => $bulto->motivo_bloqueo,
                    ]);
                    MovimientoInventarioMaterial::create([
                        'folio_id' => $folio->id,
                        'item_material_id' => $item->id,
                        'tipo' => TipoMovimientoInventarioMaterial::IngresoRecepcion,
                        'cantidad' => $bulto->cantidad,
                        'cantidad_anterior' => 0,
                        'cantidad_resultante' => $bulto->cantidad,
                        'user_id' => $usuario->id,
                        'motivo' => 'Ingreso confirmado desde recepción de materiales.',
                        'metadatos' => [
                            'recepcion_material_id' => $recepcion->id,
                            'detalle_recepcion_material_id' => $detalle->id,
                            'bulto_recepcion_material_id' => $bulto->id,
                            'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                            'estado_ubicacion' => 'pendiente_ubicacion',
                        ],
                        'ocurrido_at' => $ahora,
                    ]);
                    $foliosGenerados[] = [
                        'id' => $folio->id,
                        'numero_folio' => $codigoFolio,
                        'item_material_id' => $item->id,
                        'cantidad' => $material->cantidad_inicial,
                        'bloqueado' => $bulto->bloqueado,
                    ];
                }
            }

            $snapshot = [
                'temporada' => [
                    'id' => $temporada->id,
                    'codigo' => $temporada->codigo,
                    'nombre' => $temporada->nombre,
                ],
                'cliente' => [
                    'id' => $cliente->id,
                    'codigo' => $cliente->codigo,
                    'codigo_folio_materiales' => $cliente->codigo_folio_materiales,
                    'nombre' => $cliente->nombre,
                ],
                'proveedor' => [
                    'id' => $proveedor->id,
                    'codigo' => $proveedor->codigo,
                    'nombre' => $proveedor->nombre,
                ],
                'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                'folios' => $foliosGenerados,
            ];
            $recepcion->update([
                'estado' => EstadoRecepcionMaterial::Confirmada,
                'version' => $recepcion->version + 1,
                'snapshot_confirmacion' => $snapshot,
                'confirmacion_operacion_id' => $operacionId,
                'confirmacion_payload_hash' => $payloadHash,
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => $ahora,
            ]);
            $this->registrarEvento(
                $recepcion,
                TipoEventoRecepcionMaterial::Confirmada,
                $usuario,
                $operacionId,
                ['folios' => $foliosGenerados],
            );

            return $this->cargar($recepcion->refresh());
        }, attempts: 3);
    }

    public function anular(
        RecepcionMaterial $recepcion,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): RecepcionMaterial {
        $motivo = trim($motivo);
        $payloadHash = $this->payloadHash([
            'recepcion_material_id' => $recepcion->id,
            'motivo' => $motivo,
        ]);

        return DB::transaction(function () use (
            $recepcion,
            $operacionId,
            $motivo,
            $usuario,
            $payloadHash,
        ): RecepcionMaterial {
            $recepcion = RecepcionMaterial::query()
                ->with([
                    'detalles.bultos.folioMaterial.folio.ubicacionActual',
                    'detalles.bultos.folioMaterial.reservas',
                    'detalles.bultos.folioMaterial.retiros',
                    'detalles.bultos.folioMaterial.correccionesItem',
                ])
                ->lockForUpdate()
                ->findOrFail($recepcion->id);

            if ($recepcion->anulacion_operacion_id !== null) {
                $mismaOperacion = $recepcion->anulacion_operacion_id === $operacionId
                    && $recepcion->anulado_por_user_id === $usuario->id
                    && hash_equals((string) $recepcion->anulacion_payload_hash, $payloadHash);

                if (! $mismaOperacion) {
                    throw new ConflictoOperacion(
                        'La recepción ya fue anulada con una operación diferente.',
                    );
                }

                return $this->cargar($recepcion);
            }

            if ($recepcion->estado === EstadoRecepcionMaterial::Anulada) {
                throw new DomainException('La recepción ya se encontraba anulada.');
            }

            $ahora = now();

            if ($recepcion->estado === EstadoRecepcionMaterial::Confirmada) {
                foreach ($recepcion->detalles->flatMap->bultos as $bulto) {
                    $material = $bulto->folioMaterial;

                    if (! $material || ! $material->folio) {
                        throw new DomainException(
                            'La recepción confirmada posee un bulto sin folio de inventario.',
                        );
                    }

                    if ($material->folio->ubicacionActual) {
                        throw new DomainException(
                            'No se puede anular una recepción con folios ya ubicados.',
                        );
                    }

                    if ($material->reservas
                        ->contains(fn ($reserva): bool => $reserva->estado === EstadoReservaMaterial::Activa)) {
                        throw new DomainException(
                            'No se puede anular una recepción con reservas activas.',
                        );
                    }

                    if ($material->retiros->isNotEmpty() || $material->correccionesItem->isNotEmpty()) {
                        throw new DomainException(
                            'No se puede anular una recepción con retiros o correcciones posteriores.',
                        );
                    }

                    if (abs((float) $material->cantidad_actual - (float) $material->cantidad_inicial) > 0.0001
                        || (float) $material->cantidad_reservada > 0.0001) {
                        throw new DomainException(
                            'La recepción posee folios cuyo saldo ya fue modificado.',
                        );
                    }

                    MovimientoInventarioMaterial::create([
                        'folio_id' => $material->folio_id,
                        'item_material_id' => $material->item_material_id,
                        'tipo' => TipoMovimientoInventarioMaterial::AnulacionRecepcion,
                        'cantidad' => -(float) $material->cantidad_actual,
                        'cantidad_anterior' => $material->cantidad_actual,
                        'cantidad_resultante' => 0,
                        'user_id' => $usuario->id,
                        'motivo' => $motivo,
                        'metadatos' => [
                            'recepcion_material_id' => $recepcion->id,
                            'bulto_recepcion_material_id' => $bulto->id,
                            'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                        ],
                        'ocurrido_at' => $ahora,
                    ]);
                    $material->update([
                        'cantidad_actual' => 0,
                        'cantidad_reservada' => 0,
                    ]);
                    $material->folio->update([
                        'estado_operacional' => EstadoOperacionalFolio::Anulado,
                        'activo' => false,
                    ]);
                }
            }

            $recepcion->update([
                'estado' => EstadoRecepcionMaterial::Anulada,
                'version' => $recepcion->version + 1,
                'anulacion_operacion_id' => $operacionId,
                'anulacion_payload_hash' => $payloadHash,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => $ahora,
                'motivo_anulacion' => $motivo,
            ]);
            $this->registrarEvento(
                $recepcion,
                TipoEventoRecepcionMaterial::Anulada,
                $usuario,
                $operacionId,
                observacion: $motivo,
            );

            return $this->cargar($recepcion->refresh());
        }, attempts: 3);
    }

    public function cargar(RecepcionMaterial $recepcion): RecepcionMaterial
    {
        return $recepcion->load([
            'temporada:id,codigo,nombre,activa',
            'cliente:id,codigo,nombre,codigo_folio_materiales,activo',
            'proveedor:id,codigo,nombre,activo',
            'creadoPor:id,name',
            'confirmadoPor:id,name',
            'anuladoPor:id,name',
            'detalles.item.cliente.cliente:id,codigo,nombre',
            'detalles.bultos.folioMaterial.folio.ubicacionActual.posicion.camara',
            'eventos.usuario:id,name',
        ]);
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function crearDetalle(
        RecepcionMaterial $recepcion,
        array $linea,
        Cliente $cliente,
        string $temporadaId,
    ): void {
        $item = $this->validarItem($linea['item_material_id'], $cliente, $temporadaId);
        $cantidadDocumental = $this->cantidad($linea['cantidad_documental']);
        $cantidadRecibida = $this->cantidad($linea['cantidad_recibida']);
        $cantidadRechazada = $this->cantidad($linea['cantidad_rechazada'] ?? 0, permitirCero: true);

        if ($cantidadRecibida <= 0) {
            throw new DomainException('La cantidad recibida debe ser mayor que cero.');
        }

        $detalle = DetalleRecepcionMaterial::create([
            'recepcion_material_id' => $recepcion->id,
            'item_material_id' => $item->id,
            'categoria_operacional' => $item->categoria_operacional,
            'unidad_medida' => $item->unidad_medida,
            'cantidad_documental' => $cantidadDocumental,
            'cantidad_recibida' => $cantidadRecibida,
            'cantidad_rechazada' => $cantidadRechazada,
            'observacion' => $this->textoOpcional($linea['observacion'] ?? null),
        ]);
        $sumaBultos = 0.0;

        foreach ($linea['bultos'] as $datosBulto) {
            $cantidad = $this->cantidad($datosBulto['cantidad']);
            $bloqueado = (bool) ($datosBulto['bloqueado'] ?? false);
            $motivoBloqueo = $this->textoOpcional($datosBulto['motivo_bloqueo'] ?? null);

            if ($bloqueado && ! $motivoBloqueo) {
                throw new DomainException(
                    'Un bulto bloqueado debe registrar el motivo del bloqueo.',
                );
            }

            BultoRecepcionMaterial::create([
                'detalle_recepcion_material_id' => $detalle->id,
                'cantidad' => $cantidad,
                'lote_proveedor' => $this->textoOpcional($datosBulto['lote_proveedor'] ?? null),
                'fecha_fabricacion' => $datosBulto['fecha_fabricacion'] ?? null,
                'fecha_vencimiento' => $datosBulto['fecha_vencimiento'] ?? null,
                'bloqueado' => $bloqueado,
                'motivo_bloqueo' => $motivoBloqueo,
            ]);
            $sumaBultos = round($sumaBultos + $cantidad, 3);
        }

        if (abs($sumaBultos - $cantidadRecibida) > 0.0001) {
            throw new DomainException(sprintf(
                'La suma de bultos del ítem %s debe coincidir con la cantidad recibida.',
                $item->codigo,
            ));
        }
    }

    /**
     * @return array{Cliente, ProveedorMaterial}
     */
    private function validarCabecera(
        string $clienteId,
        string $proveedorId,
        bool $exigirCodigoFolio = false,
    ): array {
        $cliente = Cliente::query()
            ->whereKey($clienteId)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();
        $proveedor = ProveedorMaterial::query()
            ->whereKey($proveedorId)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (! $cliente) {
            throw new DomainException('El cliente no existe o se encuentra inactivo.');
        }

        if (! $proveedor) {
            throw new DomainException('El proveedor no existe o se encuentra inactivo.');
        }

        $vinculado = DB::table('clientes_proveedores_materiales')
            ->where('cliente_id', $cliente->id)
            ->where('proveedor_material_id', $proveedor->id)
            ->where('activo', true)
            ->lockForUpdate()
            ->exists();

        if (! $vinculado) {
            throw new DomainException(
                'El proveedor no se encuentra autorizado para el cliente seleccionado.',
            );
        }

        if ($exigirCodigoFolio) {
            $codigo = mb_strtoupper(trim((string) $cliente->codigo_folio_materiales));

            if (! preg_match('/^[A-Z0-9]{2}$/', $codigo)) {
                throw new DomainException(
                    'El cliente debe tener un código de folio de materiales de exactamente dos caracteres.',
                );
            }
        }

        return [$cliente, $proveedor];
    }

    private function validarItem(
        string $itemId,
        Cliente $cliente,
        string $temporadaId,
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
            throw new DomainException(
                'Uno de los ítems no pertenece al cliente y temporada de la recepción.',
            );
        }

        if (! $item->categoria_operacional) {
            throw new DomainException(sprintf(
                'El ítem %s no posee categoría operacional configurada.',
                $item->codigo,
            ));
        }

        return $item;
    }

    private function siguienteFolio(Cliente $cliente): string
    {
        $correlativo = CorrelativoMaterialCliente::query()
            ->lockForUpdate()
            ->find($cliente->id);

        if (! $correlativo) {
            $correlativo = CorrelativoMaterialCliente::create([
                'cliente_id' => $cliente->id,
                'ultimo_numero' => 0,
            ]);
        }

        $siguiente = $correlativo->ultimo_numero + 1;

        if ($siguiente > self::MAXIMO_CORRELATIVO) {
            throw new DomainException(
                'El cliente agotó el correlativo disponible para folios de materiales.',
            );
        }

        $correlativo->update(['ultimo_numero' => $siguiente]);

        return sprintf(
            'F%s%07d',
            mb_strtoupper(trim((string) $cliente->codigo_folio_materiales)),
            $siguiente,
        );
    }

    /**
     * @param  array<string, mixed>|null  $datos
     */
    private function registrarEvento(
        RecepcionMaterial $recepcion,
        TipoEventoRecepcionMaterial $tipo,
        User $usuario,
        ?string $operacionId,
        ?array $datos = null,
        ?string $observacion = null,
    ): void {
        EventoRecepcionMaterial::create([
            'recepcion_material_id' => $recepcion->id,
            'operacion_id' => $operacionId,
            'tipo' => $tipo,
            'datos' => $datos,
            'observacion' => $observacion,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
        ]);
    }

    private function cantidad(mixed $valor, bool $permitirCero = false): float
    {
        $cantidad = round((float) $valor, 3);

        if ($cantidad < 0 || (! $permitirCero && $cantidad <= 0)) {
            throw new DomainException('Las cantidades deben ser positivas.');
        }

        return $cantidad;
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
