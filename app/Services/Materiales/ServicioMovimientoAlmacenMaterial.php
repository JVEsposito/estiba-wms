<?php

namespace App\Services\Materiales;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\TipoAlmacenMaterial;
use App\Enums\TipoMovimientoAlmacenMaterial;
use App\Exceptions\ConflictoOperacion;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\AlmacenMaterial;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\FolioMaterial;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\Posicion;
use App\Models\SaldoMaterialAlmacen;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServicioMovimientoAlmacenMaterial
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
        private readonly ServicioAlmacenMaterial $almacenes,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(
        array $datos,
        User $usuario,
        ?Dispositivo $dispositivo,
    ): MovimientoAlmacenMaterial {
        $tipo = TipoMovimientoAlmacenMaterial::from($datos['tipo']);

        if ($tipo === TipoMovimientoAlmacenMaterial::Ajuste) {
            if (! $this->alcance->puedeGestionarBloqueosMateriales($usuario)) {
                throw new OperacionNoAutorizada(
                    'Solo supervisión puede registrar ajustes de inventario.',
                );
            }
        } elseif (! $this->alcance->puedeGestionarDespachosMateriales($usuario)) {
            throw new OperacionNoAutorizada(
                'El usuario no está autorizado para mover existencias entre almacenes.',
            );
        }

        $payloadHash = $this->payloadHash($datos);

        return DB::transaction(function () use (
            $datos,
            $usuario,
            $dispositivo,
            $tipo,
            $payloadHash,
        ): MovimientoAlmacenMaterial {
            $existente = MovimientoAlmacenMaterial::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->where('secuencia', 1)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->user_id !== $usuario->id
                    || ! hash_equals($existente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El UUID de movimiento ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargar($existente);
            }

            $folio = FolioMaterial::query()
                ->with(['folio', 'item'])
                ->lockForUpdate()
                ->findOrFail($datos['folio_id']);

            return match ($tipo) {
                TipoMovimientoAlmacenMaterial::Consumo => $this->consumir(
                    $folio,
                    $datos,
                    $usuario,
                    $dispositivo,
                    $payloadHash,
                ),
                TipoMovimientoAlmacenMaterial::Ajuste => $this->ajustar(
                    $folio,
                    $datos,
                    $usuario,
                    $dispositivo,
                    $payloadHash,
                ),
                TipoMovimientoAlmacenMaterial::Devolucion,
                TipoMovimientoAlmacenMaterial::Transferencia => $this->transferir(
                    $folio,
                    $datos,
                    $tipo,
                    $usuario,
                    $dispositivo,
                    $payloadHash,
                ),
                TipoMovimientoAlmacenMaterial::Entrega => throw new DomainException(
                    'Las entregas se registran desde una solicitud de materiales.',
                ),
            };
        }, attempts: 3);
    }

    public function cargar(MovimientoAlmacenMaterial $movimiento): MovimientoAlmacenMaterial
    {
        return $movimiento->load([
            'folioMaterial.folio:id,numero_folio,estado_operacional,activo',
            'item.cliente.temporada',
            'almacenOrigen:id,codigo,nombre,tipo,centro_costo',
            'almacenDestino:id,codigo,nombre,tipo,centro_costo',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function consumir(
        FolioMaterial $folio,
        array $datos,
        User $usuario,
        ?Dispositivo $dispositivo,
        string $payloadHash,
    ): MovimientoAlmacenMaterial {
        $cantidad = $this->cantidadPositiva($datos['cantidad']);
        $almacen = AlmacenMaterial::query()
            ->whereKey($datos['almacen_origen_id'] ?? null)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (! $almacen) {
            throw new DomainException('El almacén de consumo no existe o está inactivo.');
        }

        $saldo = $this->saldoBloqueado($folio, $almacen);
        $disponible = round(
            (float) $saldo->cantidad_actual - (float) $saldo->cantidad_reservada,
            3,
        );

        if ($cantidad > $disponible + 0.0001) {
            throw new DomainException(
                'La cantidad consumida supera el saldo disponible del almacén.',
            );
        }

        $this->validarFifoConsumo(
            $folio,
            $almacen,
            trim((string) ($datos['motivo_excepcion_fifo'] ?? '')),
        );

        $anterior = (float) $saldo->cantidad_actual;
        $resultante = round($anterior - $cantidad, 3);
        $totalAnterior = (float) $folio->cantidad_actual;
        $totalResultante = round($totalAnterior - $cantidad, 3);

        $saldo->update(['cantidad_actual' => $resultante]);
        $folio->update(['cantidad_actual' => $totalResultante]);
        $this->actualizarEstadoYUbicacion($folio, $almacen, $resultante, $totalResultante);

        return $this->cargar(MovimientoAlmacenMaterial::create([
            'operacion_id' => $datos['operacion_id'],
            'secuencia' => 1,
            'payload_hash' => $payloadHash,
            'tipo' => TipoMovimientoAlmacenMaterial::Consumo,
            'folio_id' => $folio->folio_id,
            'item_material_id' => $folio->item_material_id,
            'almacen_origen_id' => $almacen->id,
            'cantidad' => $cantidad,
            'saldo_origen_anterior' => $anterior,
            'saldo_origen_resultante' => $resultante,
            'centro_costo' => $almacen->centro_costo,
            'motivo' => trim((string) $datos['motivo']),
            'documento_relacionado' => $this->textoOpcional(
                $datos['documento_relacionado'] ?? null,
            ),
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo?->id,
            'metadatos' => [
                'total_empresa_anterior' => $totalAnterior,
                'total_empresa_resultante' => $totalResultante,
                'motivo_excepcion_fifo' => $this->textoOpcional(
                    $datos['motivo_excepcion_fifo'] ?? null,
                ),
            ],
            'ocurrido_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function transferir(
        FolioMaterial $folio,
        array $datos,
        TipoMovimientoAlmacenMaterial $tipo,
        User $usuario,
        ?Dispositivo $dispositivo,
        string $payloadHash,
    ): MovimientoAlmacenMaterial {
        $cantidad = $this->cantidadPositiva($datos['cantidad']);
        $origen = AlmacenMaterial::query()
            ->whereKey($datos['almacen_origen_id'] ?? null)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();
        $destino = AlmacenMaterial::query()
            ->whereKey($datos['almacen_destino_id'] ?? null)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (! $origen || ! $destino) {
            throw new DomainException('El almacén de origen o destino no está disponible.');
        }

        if ($origen->id === $destino->id) {
            throw new DomainException('El origen y destino deben ser almacenes diferentes.');
        }

        if ($tipo === TipoMovimientoAlmacenMaterial::Devolucion
            && ($origen->tipo !== TipoAlmacenMaterial::Virtual
                || $destino->tipo !== TipoAlmacenMaterial::Fisica)) {
            throw new DomainException(
                'Una devolución debe salir de una bodega virtual y regresar a una bodega física.',
            );
        }

        $saldoOrigen = $this->saldoBloqueado($folio, $origen);
        $saldoDestino = $this->almacenes->saldo($folio, $destino);
        $disponible = round(
            (float) $saldoOrigen->cantidad_actual - (float) $saldoOrigen->cantidad_reservada,
            3,
        );

        if ($cantidad > $disponible + 0.0001) {
            throw new DomainException(
                'La transferencia supera el saldo disponible del almacén de origen.',
            );
        }

        [$camaraDestino, $posicionDestino] = $this->resolverUbicacionDestino(
            $destino,
            $saldoDestino,
            $datos,
        );

        $origenAnterior = (float) $saldoOrigen->cantidad_actual;
        $origenResultante = round($origenAnterior - $cantidad, 3);
        $destinoAnterior = (float) $saldoDestino->cantidad_actual;
        $destinoResultante = round($destinoAnterior + $cantidad, 3);

        $saldoOrigen->update([
            'cantidad_actual' => $origenResultante,
            'camara_id' => $origenResultante > 0 ? $saldoOrigen->camara_id : null,
            'posicion_id' => $origenResultante > 0 ? $saldoOrigen->posicion_id : null,
        ]);
        $saldoDestino->update([
            'cantidad_actual' => $destinoResultante,
            'camara_id' => $destino->tipo === TipoAlmacenMaterial::Fisica
                ? $camaraDestino?->id
                : null,
            'posicion_id' => $destino->tipo === TipoAlmacenMaterial::Fisica
                ? $posicionDestino?->id
                : null,
        ]);

        if ($origen->tipo === TipoAlmacenMaterial::Fisica && $origenResultante <= 0.0001) {
            UbicacionActual::query()->where('folio_id', $folio->folio_id)->delete();
        }

        if ($destino->tipo === TipoAlmacenMaterial::Fisica) {
            UbicacionActual::query()->updateOrCreate(
                ['folio_id' => $folio->folio_id],
                [
                    'camara_id' => $camaraDestino?->id,
                    'posicion_id' => $posicionDestino?->id,
                    'movimiento_id' => null,
                    'ubicado_at' => now(),
                ],
            );
        }

        return $this->cargar(MovimientoAlmacenMaterial::create([
            'operacion_id' => $datos['operacion_id'],
            'secuencia' => 1,
            'payload_hash' => $payloadHash,
            'tipo' => $tipo,
            'folio_id' => $folio->folio_id,
            'item_material_id' => $folio->item_material_id,
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'cantidad' => $cantidad,
            'saldo_origen_anterior' => $origenAnterior,
            'saldo_origen_resultante' => $origenResultante,
            'saldo_destino_anterior' => $destinoAnterior,
            'saldo_destino_resultante' => $destinoResultante,
            'centro_costo' => $destino->centro_costo,
            'motivo' => trim((string) ($datos['motivo'] ?? 'Transferencia interna.')),
            'documento_relacionado' => $this->textoOpcional(
                $datos['documento_relacionado'] ?? null,
            ),
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo?->id,
            'metadatos' => [
                'camara_destino_id' => $camaraDestino?->id,
                'posicion_destino_id' => $posicionDestino?->id,
                'total_empresa' => (float) $folio->cantidad_actual,
            ],
            'ocurrido_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function ajustar(
        FolioMaterial $folio,
        array $datos,
        User $usuario,
        ?Dispositivo $dispositivo,
        string $payloadHash,
    ): MovimientoAlmacenMaterial {
        $cantidad = round((float) $datos['cantidad'], 3);

        if (abs($cantidad) <= 0.0001) {
            throw new DomainException('El ajuste debe ser distinto de cero.');
        }

        $almacen = AlmacenMaterial::query()
            ->whereKey($datos['almacen_origen_id'] ?? $datos['almacen_destino_id'] ?? null)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (! $almacen) {
            throw new DomainException('El almacén que se ajustará no está disponible.');
        }

        $saldo = $this->almacenes->saldo($folio, $almacen);
        $anterior = (float) $saldo->cantidad_actual;
        $resultante = round($anterior + $cantidad, 3);

        if ($resultante < -0.0001) {
            throw new DomainException('El ajuste dejaría un saldo negativo en el almacén.');
        }

        $totalAnterior = (float) $folio->cantidad_actual;
        $totalResultante = round($totalAnterior + $cantidad, 3);

        if ($totalResultante < -0.0001) {
            throw new DomainException('El ajuste dejaría negativa la existencia total.');
        }

        [$camara, $posicion] = $cantidad > 0
            ? $this->resolverUbicacionDestino($almacen, $saldo, $datos)
            : [null, null];

        $saldo->update([
            'cantidad_actual' => max(0, $resultante),
            'camara_id' => $resultante > 0 && $almacen->tipo === TipoAlmacenMaterial::Fisica
                ? ($camara?->id ?? $saldo->camara_id)
                : null,
            'posicion_id' => $resultante > 0 && $almacen->tipo === TipoAlmacenMaterial::Fisica
                ? ($posicion?->id ?? $saldo->posicion_id)
                : null,
        ]);
        $folio->update(['cantidad_actual' => max(0, $totalResultante)]);
        $this->actualizarEstadoYUbicacion(
            $folio,
            $almacen,
            max(0, $resultante),
            max(0, $totalResultante),
        );

        return $this->cargar(MovimientoAlmacenMaterial::create([
            'operacion_id' => $datos['operacion_id'],
            'secuencia' => 1,
            'payload_hash' => $payloadHash,
            'tipo' => TipoMovimientoAlmacenMaterial::Ajuste,
            'folio_id' => $folio->folio_id,
            'item_material_id' => $folio->item_material_id,
            'almacen_origen_id' => $cantidad < 0 ? $almacen->id : null,
            'almacen_destino_id' => $cantidad > 0 ? $almacen->id : null,
            'cantidad' => $cantidad,
            'saldo_origen_anterior' => $cantidad < 0 ? $anterior : null,
            'saldo_origen_resultante' => $cantidad < 0 ? max(0, $resultante) : null,
            'saldo_destino_anterior' => $cantidad > 0 ? $anterior : null,
            'saldo_destino_resultante' => $cantidad > 0 ? $resultante : null,
            'centro_costo' => $almacen->centro_costo,
            'motivo' => trim((string) $datos['motivo']),
            'documento_relacionado' => $this->textoOpcional(
                $datos['documento_relacionado'] ?? null,
            ),
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo?->id,
            'metadatos' => [
                'total_empresa_anterior' => $totalAnterior,
                'total_empresa_resultante' => max(0, $totalResultante),
                'requiere_autorizacion_supervision' => true,
            ],
            'ocurrido_at' => now(),
        ]));
    }

    private function saldoBloqueado(
        FolioMaterial $folio,
        AlmacenMaterial $almacen,
    ): SaldoMaterialAlmacen {
        $saldo = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->folio_id)
            ->where('almacen_material_id', $almacen->id)
            ->lockForUpdate()
            ->first();

        if (! $saldo) {
            throw new DomainException('El folio no posee saldo en el almacén indicado.');
        }

        return $saldo;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{0: ?Camara, 1: ?Posicion}
     */
    private function resolverUbicacionDestino(
        AlmacenMaterial $almacen,
        SaldoMaterialAlmacen $saldo,
        array $datos,
    ): array {
        if ($almacen->tipo !== TipoAlmacenMaterial::Fisica
            || ! $almacen->requiere_ubicacion_fisica) {
            return [null, null];
        }

        $camaraId = $datos['camara_destino_id'] ?? $saldo->camara_id;
        $posicionId = $datos['posicion_destino_id'] ?? $saldo->posicion_id;

        if (! $camaraId) {
            throw new DomainException(
                'Una bodega física requiere indicar una cámara de Materiales.',
            );
        }

        $camara = Camara::query()
            ->whereKey($camaraId)
            ->where('contenido', ContenidoCamara::Materiales->value)
            ->where('estado', EstadoCamara::Activa->value)
            ->lockForUpdate()
            ->first();

        if (! $camara) {
            throw new DomainException('La cámara de destino no es una cámara activa de Materiales.');
        }

        $posicion = null;

        if ($posicionId) {
            $posicion = Posicion::query()
                ->whereKey($posicionId)
                ->where('camara_id', $camara->id)
                ->where('estado', EstadoPosicion::Activa->value)
                ->first();

            if (! $posicion) {
                throw new DomainException(
                    'La posición no pertenece a la cámara o no se encuentra activa.',
                );
            }
        }

        return [$camara, $posicion];
    }

    private function validarFifoConsumo(
        FolioMaterial $folio,
        AlmacenMaterial $almacen,
        string $motivoExcepcion,
    ): void {
        $primero = SaldoMaterialAlmacen::query()
            ->join('folios_materiales as fm', 'fm.folio_id', '=', 'saldos_materiales_almacenes.folio_id')
            ->join('folios as f', 'f.id', '=', 'fm.folio_id')
            ->select('saldos_materiales_almacenes.folio_id')
            ->where('saldos_materiales_almacenes.almacen_material_id', $almacen->id)
            ->where('fm.item_material_id', $folio->item_material_id)
            ->whereColumn(
                'saldos_materiales_almacenes.cantidad_actual',
                '>',
                'saldos_materiales_almacenes.cantidad_reservada',
            )
            ->where('f.activo', true)
            ->orderBy('f.fecha_ingreso')
            ->orderBy('f.numero_folio')
            ->orderBy('f.id')
            ->lockForUpdate()
            ->value('saldos_materiales_almacenes.folio_id');

        if ($primero !== null
            && $primero !== $folio->folio_id
            && mb_strlen($motivoExcepcion) < 5) {
            throw new DomainException(
                'El consumo no respeta FIFO en este almacén. Debe usar el folio más antiguo o justificar la excepción.',
            );
        }
    }

    private function actualizarEstadoYUbicacion(
        FolioMaterial $folio,
        AlmacenMaterial $almacen,
        float $saldoAlmacen,
        float $totalEmpresa,
    ): void {
        if ($almacen->tipo === TipoAlmacenMaterial::Fisica && $saldoAlmacen <= 0.0001) {
            UbicacionActual::query()->where('folio_id', $folio->folio_id)->delete();
        }

        if ($totalEmpresa <= 0.0001) {
            UbicacionActual::query()->where('folio_id', $folio->folio_id)->delete();
            $folio->folio->update([
                'estado_operacional' => EstadoOperacionalFolio::Agotado,
                'activo' => false,
            ]);

            return;
        }

        if (! $folio->folio->activo
            || in_array($folio->folio->estado_operacional, [
                EstadoOperacionalFolio::Agotado,
                EstadoOperacionalFolio::Despachado,
            ], true)) {
            $folio->folio->update([
                'estado_operacional' => $folio->motivo_bloqueo
                    ? EstadoOperacionalFolio::Bloqueado
                    : EstadoOperacionalFolio::Disponible,
                'activo' => true,
            ]);
        }
    }

    private function cantidadPositiva(mixed $valor): float
    {
        $cantidad = round((float) $valor, 3);

        if ($cantidad <= 0) {
            throw new DomainException('La cantidad debe ser mayor que cero.');
        }

        return $cantidad;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->normalizarPayload($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function normalizarPayload(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            $normalizado = array_map(
                fn (mixed $item): mixed => $this->normalizarPayload($item),
                $valor,
            );
            usort($normalizado, fn (mixed $a, mixed $b): int => strcmp(
                json_encode($a, JSON_THROW_ON_ERROR),
                json_encode($b, JSON_THROW_ON_ERROR),
            ));

            return $normalizado;
        }

        ksort($valor, SORT_STRING);

        return array_map(
            fn (mixed $item): mixed => $this->normalizarPayload($item),
            $valor,
        );
    }
}
