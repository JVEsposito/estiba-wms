<?php

namespace App\Services\Envases;

use App\Enums\EstadoGuiaDespachoEnvase;
use App\Enums\EstadoRevisionMovimientoEnvase;
use App\Enums\PropiedadEnvase;
use App\Enums\TipoMovimientoEnvase;
use App\Exceptions\ConflictoOperacion;
use App\Models\Cliente;
use App\Models\DetalleGuiaDespachoEnvase;
use App\Models\GuiaDespachoEnvase;
use App\Models\MovimientoEnvase;
use App\Models\Temporada;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            $temporada = Temporada::query()->where('activa', true)->lockForUpdate()->first();
            if (! $temporada) {
                throw new ConflictoOperacion('No existe una temporada global activa para el despacho.');
            }
            $cliente = Cliente::query()->whereKey($payload['cliente_id'])->where('activo', true)->first();
            if (! $cliente) {
                throw new ConflictoOperacion('El cliente no está activo para recibir envases.');
            }
            $salida = CarbonImmutable::parse($datos['salida_at']);
            $guia = GuiaDespachoEnvase::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                'numero' => $this->siguienteNumero($salida),
                'temporada_id' => $temporada->id,
                'cliente_id' => $cliente->id,
                'estado' => EstadoGuiaDespachoEnvase::Borrador,
                'salida_at' => $salida,
                'patente_camion' => $payload['patente_camion'],
                'rut_conductor' => $payload['rut_conductor'],
                'nombre_conductor' => $payload['nombre_conductor'],
                'observacion' => $payload['observacion'],
                'creado_por_user_id' => $usuario->id,
            ]);
            foreach ($payload['detalles'] as $detalle) {
                $origen = ! empty($detalle['movimiento_origen_id'])
                    ? MovimientoEnvase::query()->find($detalle['movimiento_origen_id'])
                    : null;
                DetalleGuiaDespachoEnvase::create([
                    'guia_despacho_envase_id' => $guia->id,
                    'tipo_envase' => $detalle['tipo_envase'],
                    'cantidad' => $detalle['cantidad'],
                    'propiedad' => $detalle['propiedad'],
                    'movimiento_origen_id' => $origen?->id,
                    'origen_snapshot' => $origen ? $origen->numero_documento.' · '.$origen->cliente?->nombre : 'Existencia propia',
                ]);
            }

            return $this->cargar($guia);
        }, attempts: 3);
    }

    public function confirmar(GuiaDespachoEnvase $guia, User $usuario): GuiaDespachoEnvase
    {
        return DB::transaction(function () use ($guia, $usuario): GuiaDespachoEnvase {
            $guia = GuiaDespachoEnvase::query()
                ->whereHas('temporada', fn ($temporada) => $temporada->where('activa', true))
                ->with('detalles.movimientoOrigen')
                ->lockForUpdate()
                ->findOrFail($guia->id);
            if ($guia->estado === EstadoGuiaDespachoEnvase::Confirmada) {
                return $this->cargar($guia);
            }
            if ($guia->estado !== EstadoGuiaDespachoEnvase::Borrador) {
                throw new ConflictoOperacion('Solo una guía en borrador puede confirmarse.');
            }
            $this->asegurarDisponibilidad($guia);
            $ahora = now();
            foreach ($guia->detalles as $detalle) {
                MovimientoEnvase::create([
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
                    'datos' => ['guia_id' => $guia->id, 'confirmado_at' => $ahora->toAtomString()],
                ]);
            }
            $guia->update([
                'estado' => EstadoGuiaDespachoEnvase::Confirmada,
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => $ahora,
                'version' => $guia->version + 1,
            ]);

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
            foreach ($movimientos as $movimiento) {
                MovimientoEnvase::create([
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
                    'datos' => ['reversa_de_movimiento_id' => $movimiento->id, 'motivo' => $motivo],
                ]);
            }
            $guia->update([
                'estado' => EstadoGuiaDespachoEnvase::Anulada,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => $ahora,
                'motivo_anulacion' => $motivo,
                'version' => $guia->version + 1,
            ]);

            return $this->cargar($guia);
        }, attempts: 3);
    }

    private function asegurarDisponibilidad(GuiaDespachoEnvase $guia): void
    {
        $guia->detalles
            ->filter(fn (DetalleGuiaDespachoEnvase $detalle): bool => $detalle->propiedad === PropiedadEnvase::Propia)
            ->groupBy(fn (DetalleGuiaDespachoEnvase $detalle): string => $detalle->tipo_envase->value)
            ->each(function ($detalles, string $tipoEnvase) use ($guia): void {
                $solicitado = $detalles->sum('cantidad');
                $disponible = MovimientoEnvase::query()
                    ->where('temporada_id', $guia->temporada_id)
                    ->where('tipo_envase', $tipoEnvase)
                    ->where('propiedad', PropiedadEnvase::Propia->value)
                    ->lockForUpdate()
                    ->get(['cantidad', 'signo_existencia'])
                    ->sum(fn (MovimientoEnvase $movimiento): int => $movimiento->cantidad * $movimiento->signo_existencia);

                if ($disponible < $solicitado) {
                    throw new ConflictoOperacion('Las líneas de envases propios superan en conjunto la existencia disponible.');
                }
            });

        foreach ($guia->detalles as $detalle) {
            if ($detalle->propiedad !== PropiedadEnvase::Propia && ! $detalle->movimiento_origen_id) {
                throw new ConflictoOperacion('Los envases de cliente o arrendados deben indicar su movimiento de origen.');
            }
            if (! $detalle->movimiento_origen_id) {
                continue;
            }

            $origen = MovimientoEnvase::query()->lockForUpdate()->findOrFail($detalle->movimiento_origen_id);
            if ($origen->temporada_id !== $guia->temporada_id) {
                throw new ConflictoOperacion('El movimiento de origen pertenece a otra temporada.');
            }
            if ($origen->signo_existencia !== 1
                || $origen->tipo_envase !== $detalle->tipo_envase
                || $origen->propiedad !== $detalle->propiedad) {
                throw new ConflictoOperacion('El origen seleccionado no corresponde al tipo y propiedad de la línea.');
            }
            if ($detalle->propiedad === PropiedadEnvase::Cliente && $origen->cliente_id !== $guia->cliente_id) {
                throw new ConflictoOperacion('Los envases propios del cliente solo pueden devolverse a su titular.');
            }
            $consumido = MovimientoEnvase::query()
                ->where('movimiento_origen_id', $origen->id)
                ->where('temporada_id', $guia->temporada_id)
                ->lockForUpdate()
                ->get(['cantidad', 'signo_existencia'])
                ->sum(fn (MovimientoEnvase $movimiento): int => $movimiento->cantidad * $movimiento->signo_existencia);
            if (($origen->cantidad + $consumido) < $detalle->cantidad) {
                throw new ConflictoOperacion('La línea supera el saldo disponible del movimiento de origen.');
            }
        }
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
        DB::table('correlativos_guias_despacho_envases')->insertOrIgnore(['periodo' => $periodo, 'ultimo_numero' => 0, 'created_at' => $fecha, 'updated_at' => $fecha]);
        $correlativo = DB::table('correlativos_guias_despacho_envases')->where('periodo', $periodo)->lockForUpdate()->first();
        $siguiente = ((int) $correlativo->ultimo_numero) + 1;
        DB::table('correlativos_guias_despacho_envases')->where('periodo', $periodo)->update(['ultimo_numero' => $siguiente, 'updated_at' => $fecha]);

        return sprintf('GDE-%s-%04d', $periodo, $siguiente);
    }

    private function cargar(GuiaDespachoEnvase $guia): GuiaDespachoEnvase
    {
        return $guia->refresh()->load(['temporada', 'cliente', 'detalles.movimientoOrigen.cliente', 'creadoPor', 'confirmadoPor', 'anuladoPor']);
    }
}
