<?php

namespace App\Services\Envases;

use App\Enums\EstadoGuiaDespachoEnvase;
use App\Enums\EstadoRevisionMovimientoEnvase;
use App\Enums\PropiedadEnvase;
use App\Enums\TipoMovimientoEnvase;
use App\Exceptions\ConflictoOperacion;
use App\Models\Cliente;
use App\Models\DetalleGuiaDespachoEnvase;
use App\Models\EventoGuiaDespachoEnvase;
use App\Models\GuiaDespachoEnvase;
use App\Models\MovimientoEnvase;
use App\Models\Temporada;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class ServicioGuiaDespachoEnvases
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos, User $usuario): GuiaDespachoEnvase
    {
        $payload = $this->normalizarPayload($datos);
        $hash = $this->hashPayload($payload);

        return DB::transaction(function () use ($datos, $usuario, $payload, $hash): GuiaDespachoEnvase {
            $existente = GuiaDespachoEnvase::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();
            if ($existente) {
                $this->asegurarMismoPayload($existente, $hash);

                return $this->cargar($existente);
            }

            $temporada = $this->temporadaActiva();
            $cliente = $this->clienteActivo($payload['cliente_id']);
            $salida = CarbonImmutable::parse($payload['salida_at']);
            $guia = GuiaDespachoEnvase::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                'numero' => $this->siguienteNumero($salida),
                'temporada_id' => $temporada->id,
                'temporada_codigo_snapshot' => $temporada->codigo,
                'temporada_nombre_snapshot' => $temporada->nombre,
                'cliente_id' => $cliente->id,
                'cliente_codigo_snapshot' => $cliente->codigo,
                'cliente_nombre_snapshot' => $cliente->nombre,
                'estado' => EstadoGuiaDespachoEnvase::Borrador,
                'salida_at' => $salida,
                'patente_camion' => $payload['patente_camion'],
                'rut_conductor' => $payload['rut_conductor'],
                'nombre_conductor' => $payload['nombre_conductor'],
                'observacion' => $payload['observacion'],
                'creado_por_user_id' => $usuario->id,
            ]);

            $asignaciones = $this->asignarOrigenes(
                $payload['detalles'],
                $temporada,
                $cliente,
            );
            $this->guardarDetalles($guia, $asignaciones);
            $this->registrarEvento(
                $guia,
                'creada',
                null,
                EstadoGuiaDespachoEnvase::Borrador,
                $usuario,
                ['reserva' => $this->resumenAsignaciones($asignaciones)],
            );

            return $this->cargar($guia);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(GuiaDespachoEnvase $guia, array $datos, User $usuario): GuiaDespachoEnvase
    {
        $payload = $this->normalizarPayload($datos);

        return DB::transaction(function () use ($guia, $datos, $usuario, $payload): GuiaDespachoEnvase {
            $guia = GuiaDespachoEnvase::query()
                ->whereHas('temporada', fn ($temporada) => $temporada->where('activa', true))
                ->with('detalles.movimientoOrigen.cliente')
                ->lockForUpdate()
                ->findOrFail($guia->id);
            if ($guia->estado !== EstadoGuiaDespachoEnvase::Borrador) {
                throw new ConflictoOperacion('Solo una guía en borrador puede editarse.');
            }
            if ((int) $datos['version'] !== $guia->version) {
                throw new ConflictoOperacion('La guía cambió desde la última lectura. Actualiza antes de editar.');
            }

            $temporada = $this->temporadaActiva();
            $cliente = $this->clienteActivo($payload['cliente_id']);
            $asignaciones = $this->asignarOrigenes(
                $payload['detalles'],
                $temporada,
                $cliente,
                $guia->id,
            );
            $anterior = $this->snapshotOperacional($guia);

            DB::table('detalles_guias_despacho_envases')
                ->where('guia_despacho_envase_id', $guia->id)
                ->delete();
            $guia->update([
                'payload_hash' => $this->hashPayload($payload),
                'cliente_id' => $cliente->id,
                'cliente_codigo_snapshot' => $cliente->codigo,
                'cliente_nombre_snapshot' => $cliente->nombre,
                'salida_at' => CarbonImmutable::parse($payload['salida_at']),
                'patente_camion' => $payload['patente_camion'],
                'rut_conductor' => $payload['rut_conductor'],
                'nombre_conductor' => $payload['nombre_conductor'],
                'observacion' => $payload['observacion'],
                'version' => $guia->version + 1,
            ]);
            $this->guardarDetalles($guia, $asignaciones);
            $this->registrarEvento(
                $guia,
                'editada',
                EstadoGuiaDespachoEnvase::Borrador,
                EstadoGuiaDespachoEnvase::Borrador,
                $usuario,
                [
                    'anterior' => $anterior,
                    'reserva' => $this->resumenAsignaciones($asignaciones),
                ],
            );

            return $this->cargar($guia);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $datos */
    public function confirmar(GuiaDespachoEnvase $guia, User $usuario, array $datos = []): GuiaDespachoEnvase
    {
        return DB::transaction(function () use ($guia, $usuario, $datos): GuiaDespachoEnvase {
            $guia = GuiaDespachoEnvase::query()
                ->whereHas('temporada', fn ($temporada) => $temporada->where('activa', true))
                ->with(['detalles.movimientoOrigen.cliente', 'temporada', 'cliente', 'creadoPor'])
                ->lockForUpdate()
                ->findOrFail($guia->id);
            if ($guia->estado === EstadoGuiaDespachoEnvase::Confirmada) {
                return $this->cargar($guia);
            }
            if ($guia->estado !== EstadoGuiaDespachoEnvase::Borrador) {
                throw new ConflictoOperacion('Solo una guía en borrador puede confirmarse.');
            }
            if (isset($datos['version']) && (int) $datos['version'] !== $guia->version) {
                throw new ConflictoOperacion('La guía cambió desde la última lectura. Actualiza antes de confirmar.');
            }

            if (! empty($datos['salida_at'])) {
                $guia->salida_at = CarbonImmutable::parse($datos['salida_at']);
            }
            $this->asegurarReservaConfirmable($guia);

            $ahora = now();
            $movimientos = [];
            foreach ($guia->detalles as $detalle) {
                $movimientos[] = MovimientoEnvase::create([
                    'operacion_id' => (string) Str::uuid(),
                    'temporada_id' => $guia->temporada_id,
                    'cliente_id' => $guia->cliente_id,
                    'documento_tipo' => 'guia_despacho_envases',
                    'documento_id' => $guia->id,
                    'numero_documento' => $guia->numero,
                    'tipo_movimiento' => TipoMovimientoEnvase::DespachoCliente,
                    'tipo_envase' => $detalle->tipo_envase,
                    'cantidad' => $detalle->cantidad,
                    'signo_cuenta' => -1,
                    'signo_existencia' => -1,
                    'propiedad' => $detalle->propiedad,
                    'movimiento_origen_id' => $detalle->movimiento_origen_id,
                    'ocurrido_at' => $guia->salida_at,
                    'salida_at' => $guia->salida_at,
                    'estado_revision' => EstadoRevisionMovimientoEnvase::Pendiente,
                    'creado_por_user_id' => $usuario->id,
                    'datos' => [
                        'guia_id' => $guia->id,
                        'confirmado_at' => $ahora->toAtomString(),
                        'detalle_guia_id' => $detalle->id,
                    ],
                ]);
            }

            $snapshot = $this->snapshotDocumento($guia, $usuario, $ahora);
            $guia->update([
                'salida_at' => $guia->salida_at,
                'estado' => EstadoGuiaDespachoEnvase::Confirmada,
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => $ahora,
                'documento_snapshot' => $snapshot,
                'documento_hash' => $this->hashPayload($snapshot),
                'documento_generado_at' => $ahora,
                'version' => $guia->version + 1,
            ]);
            $this->registrarEvento(
                $guia,
                'confirmada',
                EstadoGuiaDespachoEnvase::Borrador,
                EstadoGuiaDespachoEnvase::Confirmada,
                $usuario,
                [
                    'movimientos' => collect($movimientos)->pluck('id')->all(),
                    'documento_hash' => $guia->documento_hash,
                ],
            );

            return $this->cargar($guia);
        }, attempts: 3);
    }

    public function cancelar(
        GuiaDespachoEnvase $guia,
        string $motivo,
        User $usuario,
    ): GuiaDespachoEnvase {
        return DB::transaction(function () use ($guia, $motivo, $usuario): GuiaDespachoEnvase {
            $guia = GuiaDespachoEnvase::query()
                ->whereHas('temporada', fn ($temporada) => $temporada->where('activa', true))
                ->with('detalles')
                ->lockForUpdate()
                ->findOrFail($guia->id);
            if ($guia->estado === EstadoGuiaDespachoEnvase::Cancelada) {
                return $this->cargar($guia);
            }
            if ($guia->estado !== EstadoGuiaDespachoEnvase::Borrador) {
                throw new ConflictoOperacion('Solo una guía en borrador puede cancelarse sin movimientos.');
            }

            $ahora = now();
            $guia->update([
                'estado' => EstadoGuiaDespachoEnvase::Cancelada,
                'cancelado_por_user_id' => $usuario->id,
                'cancelado_at' => $ahora,
                'motivo_cancelacion' => $motivo,
                'version' => $guia->version + 1,
            ]);
            $this->registrarEvento(
                $guia,
                'cancelada',
                EstadoGuiaDespachoEnvase::Borrador,
                EstadoGuiaDespachoEnvase::Cancelada,
                $usuario,
                ['motivo' => $motivo],
            );

            return $this->cargar($guia);
        }, attempts: 3);
    }

    public function anular(GuiaDespachoEnvase $guia, string $motivo, User $usuario): GuiaDespachoEnvase
    {
        return DB::transaction(function () use ($guia, $motivo, $usuario): GuiaDespachoEnvase {
            $guia = GuiaDespachoEnvase::query()
                ->whereHas('temporada', fn ($temporada) => $temporada->where('activa', true))
                ->lockForUpdate()
                ->findOrFail($guia->id);
            if ($guia->estado === EstadoGuiaDespachoEnvase::Anulada) {
                return $this->cargar($guia);
            }
            if ($guia->estado !== EstadoGuiaDespachoEnvase::Confirmada) {
                throw new ConflictoOperacion('Solo una guía confirmada puede anularse con reversa.');
            }

            $movimientos = MovimientoEnvase::query()
                ->where('documento_tipo', 'guia_despacho_envases')
                ->where('documento_id', $guia->id)
                ->where('tipo_movimiento', TipoMovimientoEnvase::DespachoCliente->value)
                ->lockForUpdate()
                ->get();
            $ahora = now();
            $reversas = [];
            foreach ($movimientos as $movimiento) {
                $reversas[] = MovimientoEnvase::create([
                    'operacion_id' => (string) Str::uuid(),
                    'temporada_id' => $guia->temporada_id,
                    'cliente_id' => $guia->cliente_id,
                    'documento_tipo' => 'guia_despacho_envases',
                    'documento_id' => $guia->id,
                    'numero_documento' => $guia->numero,
                    'tipo_movimiento' => TipoMovimientoEnvase::ReversionDespacho,
                    'tipo_envase' => $movimiento->tipo_envase,
                    'cantidad' => $movimiento->cantidad,
                    'signo_cuenta' => 1,
                    'signo_existencia' => 1,
                    'propiedad' => $movimiento->propiedad,
                    'movimiento_origen_id' => $movimiento->movimiento_origen_id,
                    'ocurrido_at' => $ahora,
                    'ingreso_at' => $ahora,
                    'estado_revision' => EstadoRevisionMovimientoEnvase::Pendiente,
                    'creado_por_user_id' => $usuario->id,
                    'datos' => [
                        'reversa_de_movimiento_id' => $movimiento->id,
                        'motivo' => $motivo,
                    ],
                ]);
            }
            $guia->update([
                'estado' => EstadoGuiaDespachoEnvase::Anulada,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => $ahora,
                'motivo_anulacion' => $motivo,
                'version' => $guia->version + 1,
            ]);
            $this->registrarEvento(
                $guia,
                'anulada',
                EstadoGuiaDespachoEnvase::Confirmada,
                EstadoGuiaDespachoEnvase::Anulada,
                $usuario,
                [
                    'motivo' => $motivo,
                    'reversas' => collect($reversas)->pluck('id')->all(),
                ],
            );

            return $this->cargar($guia);
        }, attempts: 3);
    }

    /**
     * @return array{
     *     origenes: array<int, array<string, mixed>>,
     *     resumen: array<int, array<string, mixed>>
     * }
     */
    public function inventario(Temporada $temporada): array
    {
        $origenes = MovimientoEnvase::query()
            ->where('temporada_id', $temporada->id)
            ->where('signo_existencia', 1)
            ->whereNull('movimiento_origen_id')
            ->with('cliente:id,codigo,nombre')
            ->orderBy('ocurrido_at')
            ->limit(1000)
            ->get()
            ->map(function (MovimientoEnvase $origen) use ($temporada): array {
                $fisico = $this->saldoFisicoOrigen($origen, $temporada->id);
                $reservado = $this->reservadoOrigen($origen->id, $temporada->id);

                return [
                    'id' => $origen->id,
                    'tipo_envase' => $origen->tipo_envase->value,
                    'propiedad' => $origen->propiedad->value,
                    'fisico' => $fisico,
                    'reservado' => $reservado,
                    'disponible' => $fisico - $reservado,
                    'documento' => $origen->numero_documento,
                    'documento_tipo' => $origen->documento_tipo,
                    'cliente' => $origen->cliente ? [
                        'id' => $origen->cliente->id,
                        'codigo' => $origen->cliente->codigo,
                        'nombre' => $origen->cliente->nombre,
                    ] : null,
                    'ingreso_at' => $origen->ingreso_at?->toAtomString()
                        ?? $origen->ocurrido_at?->toAtomString(),
                ];
            })
            ->filter(fn (array $origen): bool => $origen['fisico'] > 0 || $origen['reservado'] > 0)
            ->values();

        $resumen = $origenes
            ->groupBy(fn (array $origen): string => $origen['tipo_envase'].'|'.$origen['propiedad'])
            ->map(function (Collection $grupo, string $clave): array {
                [$tipo, $propiedad] = explode('|', $clave, 2);

                return [
                    'tipo_envase' => $tipo,
                    'propiedad' => $propiedad,
                    'fisico' => $grupo->sum('fisico'),
                    'reservado' => $grupo->sum('reservado'),
                    'disponible' => $grupo->sum('disponible'),
                ];
            })
            ->values()
            ->all();

        return ['origenes' => $origenes->all(), 'resumen' => $resumen];
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array<string, mixed>>
     */
    private function asignarOrigenes(
        array $detalles,
        Temporada $temporada,
        Cliente $cliente,
        ?string $excluirGuiaId = null,
    ): array {
        $asignaciones = [];
        $comprometido = [];
        foreach ($detalles as $detalle) {
            $cantidadPendiente = (int) $detalle['cantidad'];
            $propiedad = PropiedadEnvase::from($detalle['propiedad']);
            $origenId = $detalle['movimiento_origen_id'] ?? null;

            if ($origenId) {
                $origen = MovimientoEnvase::query()->lockForUpdate()->findOrFail($origenId);
                $this->validarOrigen($origen, $temporada, $cliente, $detalle);
                $disponible = $this->disponibleOrigen($origen, $temporada->id, $excluirGuiaId)
                    - ($comprometido[$origen->id] ?? 0);
                if ($disponible < $cantidadPendiente) {
                    throw new ConflictoOperacion(
                        "El origen {$origen->numero_documento} dispone de {$disponible} unidades; se solicitaron {$cantidadPendiente}.",
                    );
                }
                $asignaciones[] = $this->asignacion($detalle, $origen, $cantidadPendiente);
                $comprometido[$origen->id] = ($comprometido[$origen->id] ?? 0)
                    + $cantidadPendiente;

                continue;
            }

            $origenes = MovimientoEnvase::query()
                ->where('temporada_id', $temporada->id)
                ->where('tipo_envase', $detalle['tipo_envase'])
                ->where('propiedad', $propiedad->value)
                ->where('signo_existencia', 1)
                ->whereNull('movimiento_origen_id')
                ->when(
                    $propiedad === PropiedadEnvase::Cliente,
                    fn ($consulta) => $consulta->where('cliente_id', $cliente->id),
                )
                ->orderBy('ocurrido_at')
                ->lockForUpdate()
                ->get();

            foreach ($origenes as $origen) {
                $disponible = $this->disponibleOrigen($origen, $temporada->id, $excluirGuiaId)
                    - ($comprometido[$origen->id] ?? 0);
                if ($disponible <= 0) {
                    continue;
                }
                $cantidad = min($cantidadPendiente, $disponible);
                $asignaciones[] = $this->asignacion($detalle, $origen, $cantidad);
                $comprometido[$origen->id] = ($comprometido[$origen->id] ?? 0) + $cantidad;
                $cantidadPendiente -= $cantidad;
                if ($cantidadPendiente === 0) {
                    break;
                }
            }

            if ($cantidadPendiente > 0) {
                throw new ConflictoOperacion(sprintf(
                    'No existe disponibilidad suficiente de %s %s; faltan %d unidades.',
                    $detalle['tipo_envase'],
                    $propiedad->value,
                    $cantidadPendiente,
                ));
            }
        }

        return $asignaciones;
    }

    /** @param array<string, mixed> $detalle */
    private function validarOrigen(
        MovimientoEnvase $origen,
        Temporada $temporada,
        Cliente $cliente,
        array $detalle,
    ): void {
        if ($origen->temporada_id !== $temporada->id) {
            throw new ConflictoOperacion('El movimiento de origen pertenece a otra temporada.');
        }
        if ($origen->signo_existencia !== 1
            || $origen->movimiento_origen_id !== null
            || $origen->tipo_envase->value !== $detalle['tipo_envase']
            || $origen->propiedad->value !== $detalle['propiedad']) {
            throw new ConflictoOperacion('El origen seleccionado no corresponde al tipo y propiedad de la línea.');
        }
        if ($origen->propiedad === PropiedadEnvase::Cliente && $origen->cliente_id !== $cliente->id) {
            throw new ConflictoOperacion('Los envases del cliente solo pueden devolverse a su titular.');
        }
    }

    private function asegurarReservaConfirmable(GuiaDespachoEnvase $guia): void
    {
        foreach ($guia->detalles as $detalle) {
            if (! $detalle->movimiento_origen_id) {
                throw new ConflictoOperacion(
                    'La guía contiene una reserva antigua sin origen. Edítala y guárdala antes de confirmar.',
                );
            }
            $origen = MovimientoEnvase::query()->lockForUpdate()->findOrFail($detalle->movimiento_origen_id);
            $this->validarOrigen($origen, $guia->temporada, $guia->cliente, [
                'tipo_envase' => $detalle->tipo_envase->value,
                'propiedad' => $detalle->propiedad->value,
            ]);
            $disponible = $this->disponibleOrigen($origen, $guia->temporada_id, $guia->id);
            if ($disponible < $detalle->cantidad) {
                throw new ConflictoOperacion(
                    "La reserva de {$origen->numero_documento} ya no dispone de la cantidad solicitada.",
                );
            }
        }
    }

    private function saldoFisicoOrigen(MovimientoEnvase $origen, string $temporadaId): int
    {
        $ajustes = (int) MovimientoEnvase::query()
            ->where('movimiento_origen_id', $origen->id)
            ->where('temporada_id', $temporadaId)
            ->selectRaw('COALESCE(SUM(cantidad * signo_existencia), 0) as saldo')
            ->value('saldo');

        return $origen->cantidad + $ajustes;
    }

    private function reservadoOrigen(
        string $origenId,
        string $temporadaId,
        ?string $excluirGuiaId = null,
    ): int {
        return (int) DetalleGuiaDespachoEnvase::query()
            ->where('movimiento_origen_id', $origenId)
            ->whereHas('guia', function ($consulta) use ($temporadaId, $excluirGuiaId): void {
                $consulta->where('temporada_id', $temporadaId)
                    ->where('estado', EstadoGuiaDespachoEnvase::Borrador->value)
                    ->when($excluirGuiaId, fn ($q) => $q->where('id', '!=', $excluirGuiaId));
            })
            ->sum('cantidad');
    }

    private function disponibleOrigen(
        MovimientoEnvase $origen,
        string $temporadaId,
        ?string $excluirGuiaId = null,
    ): int {
        return $this->saldoFisicoOrigen($origen, $temporadaId)
            - $this->reservadoOrigen($origen->id, $temporadaId, $excluirGuiaId);
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed>
     */
    private function asignacion(array $detalle, MovimientoEnvase $origen, int $cantidad): array
    {
        $origen->loadMissing('cliente');

        return [
            'tipo_envase' => $detalle['tipo_envase'],
            'cantidad' => $cantidad,
            'propiedad' => $detalle['propiedad'],
            'movimiento_origen_id' => $origen->id,
            'origen_snapshot' => implode(' · ', array_filter([
                $origen->numero_documento,
                $origen->cliente?->nombre,
                $origen->ocurrido_at?->format('d-m-Y H:i'),
            ])),
        ];
    }

    /** @param array<int, array<string, mixed>> $detalles */
    private function guardarDetalles(GuiaDespachoEnvase $guia, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            DetalleGuiaDespachoEnvase::create([
                'guia_despacho_envase_id' => $guia->id,
                ...$detalle,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $asignaciones
     * @return array<int, array<string, mixed>>
     */
    private function resumenAsignaciones(array $asignaciones): array
    {
        return collect($asignaciones)
            ->groupBy(fn (array $linea): string => $linea['tipo_envase'].'|'.$linea['propiedad'])
            ->map(function (Collection $lineas, string $clave): array {
                [$tipo, $propiedad] = explode('|', $clave, 2);

                return [
                    'tipo_envase' => $tipo,
                    'propiedad' => $propiedad,
                    'cantidad' => $lineas->sum('cantidad'),
                    'origenes' => $lineas->pluck('movimiento_origen_id')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function snapshotDocumento(GuiaDespachoEnvase $guia, User $usuario, mixed $confirmadoAt): array
    {
        return [
            'numero' => $guia->numero,
            'estado' => EstadoGuiaDespachoEnvase::Confirmada->value,
            'temporada' => [
                'id' => $guia->temporada_id,
                'codigo' => $guia->temporada_codigo_snapshot ?: $guia->temporada->codigo,
                'nombre' => $guia->temporada_nombre_snapshot ?: $guia->temporada->nombre,
            ],
            'cliente' => [
                'id' => $guia->cliente_id,
                'codigo' => $guia->cliente_codigo_snapshot ?: $guia->cliente->codigo,
                'nombre' => $guia->cliente_nombre_snapshot ?: $guia->cliente->nombre,
            ],
            'salida_at' => $guia->salida_at?->toAtomString(),
            'confirmado_at' => $confirmadoAt->toAtomString(),
            'patente_camion' => $guia->patente_camion,
            'conductor' => [
                'rut' => $guia->rut_conductor,
                'nombre' => $guia->nombre_conductor,
            ],
            'observacion' => $guia->observacion,
            'detalles' => $guia->detalles->map(fn (DetalleGuiaDespachoEnvase $detalle): array => [
                'tipo_envase' => $detalle->tipo_envase->value,
                'cantidad' => $detalle->cantidad,
                'propiedad' => $detalle->propiedad->value,
                'movimiento_origen_id' => $detalle->movimiento_origen_id,
                'origen' => $detalle->origen_snapshot,
            ])->values()->all(),
            'creado_por' => $guia->creadoPor?->name,
            'confirmado_por' => $usuario->name,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotOperacional(GuiaDespachoEnvase $guia): array
    {
        return [
            'cliente_id' => $guia->cliente_id,
            'salida_at' => $guia->salida_at?->toAtomString(),
            'patente_camion' => $guia->patente_camion,
            'rut_conductor' => $guia->rut_conductor,
            'nombre_conductor' => $guia->nombre_conductor,
            'observacion' => $guia->observacion,
            'detalles' => $guia->detalles->map(fn (DetalleGuiaDespachoEnvase $detalle): array => [
                'tipo_envase' => $detalle->tipo_envase->value,
                'cantidad' => $detalle->cantidad,
                'propiedad' => $detalle->propiedad->value,
                'movimiento_origen_id' => $detalle->movimiento_origen_id,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $datos
     */
    private function registrarEvento(
        GuiaDespachoEnvase $guia,
        string $tipo,
        ?EstadoGuiaDespachoEnvase $anterior,
        EstadoGuiaDespachoEnvase $nuevo,
        User $usuario,
        ?array $datos = null,
    ): void {
        EventoGuiaDespachoEnvase::create([
            'guia_despacho_envase_id' => $guia->id,
            'tipo' => $tipo,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
            'datos' => $datos,
        ]);
    }

    private function temporadaActiva(): Temporada
    {
        $temporada = Temporada::query()->where('activa', true)->lockForUpdate()->first();
        if (! $temporada) {
            throw new ConflictoOperacion('No existe una temporada global activa para el despacho.');
        }

        return $temporada;
    }

    private function clienteActivo(string $clienteId): Cliente
    {
        $cliente = Cliente::query()->whereKey($clienteId)->where('activo', true)->first();
        if (! $cliente) {
            throw new ConflictoOperacion('El cliente no está activo para recibir envases.');
        }

        return $cliente;
    }

    /** @param array<string, mixed> $datos */
    private function normalizarPayload(array $datos): array
    {
        $detalles = collect($datos['detalles'])
            ->map(fn (array $detalle): array => [
                'tipo_envase' => $detalle['tipo_envase'],
                'cantidad' => (int) $detalle['cantidad'],
                'propiedad' => $detalle['propiedad'],
                'movimiento_origen_id' => $detalle['movimiento_origen_id'] ?? null,
            ])
            ->sortBy(fn (array $detalle): string => implode('|', [
                $detalle['tipo_envase'],
                $detalle['propiedad'],
                $detalle['movimiento_origen_id'] ?? '',
            ]))
            ->values();
        $duplicada = $detalles
            ->groupBy(fn (array $detalle): string => implode('|', [
                $detalle['tipo_envase'],
                $detalle['propiedad'],
                $detalle['movimiento_origen_id'] ?? '',
            ]))
            ->contains(fn ($lineas): bool => $lineas->count() > 1);
        if ($duplicada) {
            throw new ConflictoOperacion('No repitas el mismo tipo, propiedad y origen dentro de una guía.');
        }

        return [
            'cliente_id' => $datos['cliente_id'],
            'salida_at' => CarbonImmutable::parse($datos['salida_at'])->utc()->toAtomString(),
            'patente_camion' => $datos['patente_camion'] ?? null,
            'rut_conductor' => $datos['rut_conductor'] ?? null,
            'nombre_conductor' => $datos['nombre_conductor'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'detalles' => $detalles->all(),
        ];
    }

    private function asegurarMismoPayload(GuiaDespachoEnvase $guia, string $recibido): void
    {
        $existente = $guia->payload_hash;
        if ($existente === null) {
            $existente = $this->hashPayload($this->payloadDesdeGuia($guia));
            $guia->update(['payload_hash' => $existente]);
        }
        if (! hash_equals($existente, $recibido)) {
            throw new ConflictoOperacion('El identificador de operación ya fue utilizado con datos diferentes.');
        }
    }

    /** @return array<string, mixed> */
    private function payloadDesdeGuia(GuiaDespachoEnvase $guia): array
    {
        $guia->loadMissing('detalles');

        return [
            'cliente_id' => $guia->cliente_id,
            'salida_at' => CarbonImmutable::parse($guia->salida_at)->utc()->toAtomString(),
            'patente_camion' => $guia->patente_camion,
            'rut_conductor' => $guia->rut_conductor,
            'nombre_conductor' => $guia->nombre_conductor,
            'observacion' => $guia->observacion,
            'detalles' => $guia->detalles
                ->map(fn (DetalleGuiaDespachoEnvase $detalle): array => [
                    'tipo_envase' => $detalle->tipo_envase->value,
                    'cantidad' => $detalle->cantidad,
                    'propiedad' => $detalle->propiedad->value,
                    'movimiento_origen_id' => $detalle->movimiento_origen_id,
                ])
                ->sortBy(fn (array $detalle): string => implode('|', [
                    $detalle['tipo_envase'],
                    $detalle['propiedad'],
                    $detalle['movimiento_origen_id'] ?? '',
                ]))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hashPayload(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new ConflictoOperacion('No fue posible validar la operación de la guía.', previous: $exception);
        }
    }

    private function siguienteNumero(mixed $fecha): string
    {
        $periodo = $fecha->format('ym');
        DB::table('correlativos_guias_despacho_envases')->insertOrIgnore([
            'periodo' => $periodo,
            'ultimo_numero' => 0,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
        $correlativo = DB::table('correlativos_guias_despacho_envases')
            ->where('periodo', $periodo)
            ->lockForUpdate()
            ->first();
        $siguiente = ((int) $correlativo->ultimo_numero) + 1;
        DB::table('correlativos_guias_despacho_envases')
            ->where('periodo', $periodo)
            ->update(['ultimo_numero' => $siguiente, 'updated_at' => $fecha]);

        return sprintf('GDE-%s-%04d', $periodo, $siguiente);
    }

    private function cargar(GuiaDespachoEnvase $guia): GuiaDespachoEnvase
    {
        return $guia->refresh()->load([
            'temporada',
            'cliente',
            'detalles.movimientoOrigen.cliente',
            'creadoPor',
            'confirmadoPor',
            'canceladoPor',
            'anuladoPor',
        ]);
    }
}
