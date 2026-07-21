<?php

namespace App\Services\Romana;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\TipoEventoRomana;
use App\Exceptions\ConflictoOperacion;
use App\Models\Cliente;
use App\Models\EventoRecepcionRomana;
use App\Models\RecepcionRomana;
use App\Models\Temporada;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

class ServicioRecepcionRomana
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos, User $usuario): RecepcionRomana
    {
        $payload = $this->datosRecepcion($datos);
        $hash = $this->hash($payload);

        return DB::transaction(function () use ($datos, $usuario, $payload, $hash): RecepcionRomana {
            $existente = RecepcionRomana::query()->where('operacion_id', $datos['operacion_id'])->first();
            if ($existente) {
                $this->asegurarMismoPayload($existente->payload_hash, $hash);

                return $this->cargar($existente);
            }

            $temporada = $this->temporadaActiva((string) $payload['temporada_id']);
            $cliente = $this->clienteActivo((string) $payload['cliente_id']);
            $this->asegurarGuiaUnica($temporada->id, $cliente->id, (string) $payload['numero_guia_despacho']);
            $ahora = CarbonImmutable::now();
            $recepcion = RecepcionRomana::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $hash,
                'temporada_id' => $temporada->id,
                'temporada_codigo_snapshot' => $temporada->codigo,
                'temporada_nombre_snapshot' => $temporada->nombre,
                'cliente_id' => $cliente->id,
                'cliente_codigo_snapshot' => $cliente->codigo,
                'cliente_nombre_snapshot' => $cliente->nombre,
                'tipo_servicio' => $payload['tipo_servicio'],
                'cantidad_envases_declarados' => $payload['cantidad_envases_declarados'],
                'tipo_envase_declarado' => $payload['tipo_envase_declarado'],
                'numero_guia_despacho' => $payload['numero_guia_despacho'],
                'patente_camion' => $payload['patente_camion'],
                'patente_carro' => $payload['patente_carro'],
                'rut_conductor' => $payload['rut_conductor'],
                'nombre_conductor' => $payload['nombre_conductor'],
                'peso_bruto' => $payload['peso_bruto'],
                'estado' => EstadoRecepcionRomana::EnBasculaIngreso,
                'ingreso_at' => $ahora,
                'creado_por_user_id' => $usuario->id,
                'observacion' => $payload['observacion'],
            ]);

            $this->registrarEvento(
                $recepcion,
                (string) $datos['operacion_id'],
                $hash,
                TipoEventoRomana::IngresoRegistrado,
                null,
                EstadoRecepcionRomana::EnBasculaIngreso,
                $usuario,
                $ahora,
                [
                    'peso_bruto' => (float) $recepcion->peso_bruto,
                    'numero_guia_despacho' => $recepcion->numero_guia_despacho,
                    'temporada_id' => $recepcion->temporada_id,
                ],
            );

            return $this->cargar($recepcion);
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(RecepcionRomana $recepcion, array $datos, User $usuario): RecepcionRomana
    {
        $payload = $this->datosRecepcion($datos);
        $hash = $this->hash($payload);

        return DB::transaction(function () use ($recepcion, $datos, $usuario, $payload, $hash): RecepcionRomana {
            $recepcion = RecepcionRomana::query()->lockForUpdate()->findOrFail($recepcion->id);
            $evento = EventoRecepcionRomana::query()->where('operacion_id', $datos['operacion_id'])->first();
            if ($evento) {
                $this->asegurarEventoIdempotente($evento, $recepcion, $hash, TipoEventoRomana::IngresoActualizado);

                return $this->cargar($recepcion);
            }

            if (! $recepcion->estado->esEditable()) {
                throw new ConflictoOperacion('La recepción ya confirmó su ingreso y sus antecedentes no pueden editarse.');
            }

            $temporada = $this->temporadaActiva((string) $payload['temporada_id']);
            $cliente = $this->clienteActivo((string) $payload['cliente_id']);
            $this->asegurarGuiaUnica(
                $temporada->id,
                $cliente->id,
                (string) $payload['numero_guia_despacho'],
                $recepcion->id,
            );
            $recepcion->update([
                'temporada_id' => $temporada->id,
                'temporada_codigo_snapshot' => $temporada->codigo,
                'temporada_nombre_snapshot' => $temporada->nombre,
                'cliente_id' => $cliente->id,
                'cliente_codigo_snapshot' => $cliente->codigo,
                'cliente_nombre_snapshot' => $cliente->nombre,
                'tipo_servicio' => $payload['tipo_servicio'],
                'cantidad_envases_declarados' => $payload['cantidad_envases_declarados'],
                'tipo_envase_declarado' => $payload['tipo_envase_declarado'],
                'numero_guia_despacho' => $payload['numero_guia_despacho'],
                'patente_camion' => $payload['patente_camion'],
                'patente_carro' => $payload['patente_carro'],
                'rut_conductor' => $payload['rut_conductor'],
                'nombre_conductor' => $payload['nombre_conductor'],
                'peso_bruto' => $payload['peso_bruto'],
                'observacion' => $payload['observacion'],
                'version' => $recepcion->version + 1,
            ]);
            $this->registrarEvento(
                $recepcion,
                (string) $datos['operacion_id'],
                $hash,
                TipoEventoRomana::IngresoActualizado,
                EstadoRecepcionRomana::EnBasculaIngreso,
                EstadoRecepcionRomana::EnBasculaIngreso,
                $usuario,
                CarbonImmutable::now(),
                ['version' => $recepcion->version],
            );

            return $this->cargar($recepcion);
        });
    }

    public function confirmarIngreso(RecepcionRomana $recepcion, string $operacionId, User $usuario): RecepcionRomana
    {
        $hash = $this->hash(['accion' => 'confirmar_ingreso', 'recepcion_id' => $recepcion->id]);

        return DB::transaction(function () use ($recepcion, $operacionId, $usuario, $hash): RecepcionRomana {
            $recepcion = RecepcionRomana::query()->lockForUpdate()->findOrFail($recepcion->id);
            $evento = EventoRecepcionRomana::query()->where('operacion_id', $operacionId)->first();
            if ($evento) {
                $this->asegurarEventoIdempotente($evento, $recepcion, $hash, TipoEventoRomana::IngresoConfirmado);

                return $this->cargar($recepcion);
            }

            if ($recepcion->estado !== EstadoRecepcionRomana::EnBasculaIngreso) {
                throw new ConflictoOperacion('La recepción no está disponible para confirmar el pesaje de ingreso.');
            }

            $ahora = CarbonImmutable::now();
            $recepcion->update([
                'estado' => EstadoRecepcionRomana::EnBasculaSalida,
                'ingreso_confirmado_at' => $ahora,
                'ingreso_confirmado_por_user_id' => $usuario->id,
                'version' => $recepcion->version + 1,
            ]);
            $this->registrarEvento(
                $recepcion,
                $operacionId,
                $hash,
                TipoEventoRomana::IngresoConfirmado,
                EstadoRecepcionRomana::EnBasculaIngreso,
                EstadoRecepcionRomana::EnBasculaSalida,
                $usuario,
                $ahora,
                ['peso_bruto' => (float) $recepcion->peso_bruto],
            );

            return $this->cargar($recepcion);
        });
    }

    /** @param array<string, mixed> $datos */
    public function cerrar(RecepcionRomana $recepcion, array $datos, User $usuario): RecepcionRomana
    {
        $payload = [
            'accion' => 'cerrar',
            'recepcion_id' => $recepcion->id,
            'peso_tara' => round((float) $datos['peso_tara'], 2),
            'observacion' => $datos['observacion'] ?? null,
        ];
        $hash = $this->hash($payload);

        return DB::transaction(function () use ($recepcion, $datos, $usuario, $payload, $hash): RecepcionRomana {
            $recepcion = RecepcionRomana::query()->lockForUpdate()->findOrFail($recepcion->id);
            $evento = EventoRecepcionRomana::query()->where('operacion_id', $datos['operacion_id'])->first();
            if ($evento) {
                $this->asegurarEventoIdempotente($evento, $recepcion, $hash, TipoEventoRomana::RecepcionCerrada);

                return $this->cargar($recepcion);
            }

            if ($recepcion->estado !== EstadoRecepcionRomana::EnBasculaSalida) {
                throw new ConflictoOperacion('La recepción debe confirmar primero el pesaje de ingreso.');
            }

            $tara = (float) $payload['peso_tara'];
            $bruto = (float) $recepcion->peso_bruto;
            if ($tara >= $bruto) {
                throw new ConflictoOperacion('La tara debe ser menor que el peso bruto registrado.');
            }

            $ahora = CarbonImmutable::now();
            $numero = $this->siguienteNumero($ahora);
            $recepcion->update([
                'numero_recepcion' => $numero,
                'peso_tara' => $tara,
                'peso_neto' => round($bruto - $tara, 2),
                'estado' => EstadoRecepcionRomana::Cerrado,
                'salida_at' => $ahora,
                'cerrado_por_user_id' => $usuario->id,
                'observacion_cierre' => $payload['observacion'],
                'version' => $recepcion->version + 1,
            ]);
            $this->registrarEvento(
                $recepcion,
                (string) $datos['operacion_id'],
                $hash,
                TipoEventoRomana::RecepcionCerrada,
                EstadoRecepcionRomana::EnBasculaSalida,
                EstadoRecepcionRomana::Cerrado,
                $usuario,
                $ahora,
                [
                    'numero_recepcion' => $numero,
                    'peso_bruto' => $bruto,
                    'peso_tara' => $tara,
                    'peso_neto' => (float) $recepcion->peso_neto,
                    'observacion_cierre' => $payload['observacion'],
                ],
            );

            return $this->cargar($recepcion);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function datosRecepcion(array $datos): array
    {
        return [
            'temporada_id' => $datos['temporada_id'],
            'cliente_id' => $datos['cliente_id'],
            'tipo_servicio' => $datos['tipo_servicio'],
            'cantidad_envases_declarados' => (int) $datos['cantidad_envases_declarados'],
            'tipo_envase_declarado' => $datos['tipo_envase_declarado'],
            'numero_guia_despacho' => $datos['numero_guia_despacho'],
            'patente_camion' => $datos['patente_camion'],
            'patente_carro' => $datos['patente_carro'] ?? null,
            'rut_conductor' => $datos['rut_conductor'],
            'nombre_conductor' => $datos['nombre_conductor'],
            'peso_bruto' => round((float) $datos['peso_bruto'], 2),
            'observacion' => $datos['observacion'] ?? null,
        ];
    }

    private function temporadaActiva(string $temporadaId): Temporada
    {
        $temporada = Temporada::query()
            ->whereKey($temporadaId)
            ->where('activa', true)
            ->first();

        if (! $temporada) {
            throw new ConflictoOperacion('La temporada global no está activa para nuevas recepciones.');
        }

        return $temporada;
    }

    private function clienteActivo(string $clienteId): Cliente
    {
        $cliente = Cliente::query()
            ->whereKey($clienteId)
            ->where('activo', true)
            ->first();

        if (! $cliente) {
            throw new ConflictoOperacion('El cliente operacional no está activo para nuevas recepciones.');
        }

        return $cliente;
    }

    private function asegurarGuiaUnica(
        string $temporadaId,
        string $clienteId,
        string $guia,
        ?string $ignorarId = null,
    ): void
    {
        $consulta = RecepcionRomana::query()
            ->where('temporada_id', $temporadaId)
            ->where('cliente_id', $clienteId)
            ->where('numero_guia_despacho', $guia);
        if ($ignorarId) {
            $consulta->where('id', '!=', $ignorarId);
        }

        if ($consulta->exists()) {
            throw new ConflictoOperacion('La guía de despacho ya fue registrada para este cliente.');
        }
    }

    private function siguienteNumero(CarbonImmutable $fecha): string
    {
        $periodo = $fecha->format('ym');
        DB::table('correlativos_recepcion_romana')->insertOrIgnore([
            'periodo' => $periodo,
            'ultimo_numero' => 0,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
        $correlativo = DB::table('correlativos_recepcion_romana')
            ->where('periodo', $periodo)
            ->lockForUpdate()
            ->first();
        $siguiente = ((int) $correlativo->ultimo_numero) + 1;
        DB::table('correlativos_recepcion_romana')
            ->where('periodo', $periodo)
            ->update(['ultimo_numero' => $siguiente, 'updated_at' => $fecha]);

        return sprintf('REC-%s-%04d', $periodo, $siguiente);
    }

    /** @param array<string, mixed> $datos */
    private function registrarEvento(
        RecepcionRomana $recepcion,
        string $operacionId,
        string $hash,
        TipoEventoRomana $tipo,
        ?EstadoRecepcionRomana $estadoAnterior,
        EstadoRecepcionRomana $estadoNuevo,
        User $usuario,
        CarbonImmutable $ocurridoAt,
        array $datos,
    ): void {
        EventoRecepcionRomana::create([
            'operacion_id' => $operacionId,
            'payload_hash' => $hash,
            'recepcion_romana_id' => $recepcion->id,
            'tipo' => $tipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'user_id' => $usuario->id,
            'ocurrido_at' => $ocurridoAt,
            'datos' => $datos,
        ]);
    }

    private function asegurarEventoIdempotente(
        EventoRecepcionRomana $evento,
        RecepcionRomana $recepcion,
        string $hash,
        TipoEventoRomana $tipo,
    ): void {
        if ($evento->recepcion_romana_id !== $recepcion->id
            || $evento->payload_hash !== $hash
            || $evento->tipo !== $tipo) {
            throw new ConflictoOperacion('El identificador de operación ya fue utilizado con datos diferentes.');
        }
    }

    private function asegurarMismoPayload(string $existente, string $recibido): void
    {
        if (! hash_equals($existente, $recibido)) {
            throw new ConflictoOperacion('El identificador de operación ya fue utilizado con datos diferentes.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        ksort($payload);

        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new ConflictoOperacion('No fue posible validar la operación de romana.', previous: $exception);
        }
    }

    private function cargar(RecepcionRomana $recepcion): RecepcionRomana
    {
        return $recepcion->refresh()->load([
            'temporada',
            'cliente',
            'creadoPor',
            'ingresoConfirmadoPor',
            'cerradoPor',
            'eventos' => fn ($consulta) => $consulta->with('usuario')->orderBy('ocurrido_at'),
        ]);
    }
}
