<?php

namespace App\Services\MateriaPrima;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoSubloteRetornoPacking;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\EntregaFrutaProceso;
use App\Models\EventoLoteMateriaPrima;
use App\Models\LoteMateriaPrima;
use App\Models\PersonalAccessToken;
use App\Models\RetornoPacking;
use App\Models\SubloteRetornoPacking;
use App\Models\TipoResultadoPacking;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Secuencias\ServicioSecuenciaDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class ServicioRetornoPacking
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
        private readonly ServicioSecuenciaDocumento $secuencias,
    ) {}

    /** @param array<string, mixed> $datos */
    public function registrar(
        EntregaFrutaProceso $entrega,
        array $datos,
        User $usuario,
    ): LoteMateriaPrima {
        $payload = $this->payload($datos);
        $hash = $this->hash($payload);

        try {
            return DB::transaction(function () use (
                $entrega,
                $datos,
                $usuario,
                $payload,
                $hash,
            ): LoteMateriaPrima {
                $existente = RetornoPacking::query()
                    ->where('operacion_id', $datos['operacion_id'])
                    ->lockForUpdate()
                    ->first();
                if ($existente) {
                    return $this->resolverRetornoRepetido($existente, $entrega, $hash);
                }
                $this->asegurarOperacionDisponible($datos['operacion_id']);

                $entrega = EntregaFrutaProceso::query()
                    ->lockForUpdate()
                    ->findOrFail($entrega->id);
                if ($entrega->anulado_at !== null) {
                    throw new ConflictoOperacion('No se puede retornar una entrega anulada.');
                }
                $lote = $this->loteActivo($entrega->lote_materia_prima_id);
                if (RetornoPacking::query()
                    ->where('entrega_fruta_proceso_id', $entrega->id)
                    ->whereNull('anulado_at')
                    ->where('cierra_entrega', true)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new ConflictoOperacion(
                        'El retorno de este viaje ya fue cerrado por Packing.',
                    );
                }

                $tipos = $this->tiposResultado($payload['resultados']);
                $token = $usuario->currentAccessToken();
                $retorno = RetornoPacking::create([
                    'operacion_id' => $datos['operacion_id'],
                    'payload_hash' => $hash,
                    'numero' => sprintf(
                        'RP-%06d',
                        $this->secuencias->reservarSiguiente('retornos_packing'),
                    ),
                    'entrega_fruta_proceso_id' => $entrega->id,
                    'cierra_entrega' => $payload['cierra_entrega'],
                    'observacion' => $payload['observacion'],
                    'registrado_por_user_id' => $usuario->id,
                    'dispositivo_id' => $token instanceof PersonalAccessToken
                        ? $token->dispositivo_id
                        : null,
                    'registrado_at' => now(),
                ]);

                $detalleEvento = [];
                foreach ($payload['resultados'] as $resultado) {
                    $tipo = $tipos->get($resultado['tipo_resultado_packing_id']);
                    $nombre = $this->nombreResultado($tipo, $resultado);
                    $sublote = SubloteRetornoPacking::create([
                        'retorno_packing_id' => $retorno->id,
                        'tipo_resultado_packing_id' => $tipo->id,
                        'numero_sublote' => sprintf(
                            '%s-%06d',
                            strtoupper($tipo->prefijo_sublote),
                            $this->secuencias->reservarSiguiente('sublotes_packing'),
                        ),
                        'nombre_resultado' => $nombre,
                        'cantidad_bins' => $resultado['cantidad_bins'],
                        'kilos_netos' => $resultado['kilos_netos'],
                        'estado' => EstadoSubloteRetornoPacking::PendienteUbicacion,
                    ]);
                    $detalleEvento[] = [
                        'sublote_id' => $sublote->id,
                        'numero_sublote' => $sublote->numero_sublote,
                        'resultado' => $nombre,
                        'cantidad_bins' => $sublote->cantidad_bins,
                        'kilos_netos' => $sublote->kilos_netos,
                    ];
                }

                $this->registrarEvento(
                    $lote,
                    'retorno_packing_registrado',
                    $usuario,
                    $datos['operacion_id'],
                    [
                        'retorno_id' => $retorno->id,
                        'numero' => $retorno->numero,
                        'entrega_id' => $entrega->id,
                        'cierra_entrega' => $retorno->cierra_entrega,
                        'resultados' => $detalleEvento,
                    ],
                );

                return $this->cargarLote($lote);
            }, attempts: 3);
        } catch (QueryException $excepcion) {
            $existente = RetornoPacking::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->first();
            if (! $existente) {
                throw $excepcion;
            }

            return $this->resolverRetornoRepetido($existente, $entrega, $hash);
        }
    }

    public function ubicar(
        SubloteRetornoPacking $sublote,
        string $operacionId,
        string $camaraId,
        ?string $observacion,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use (
            $sublote,
            $operacionId,
            $camaraId,
            $observacion,
            $usuario,
        ): LoteMateriaPrima {
            $sublote = SubloteRetornoPacking::query()
                ->lockForUpdate()
                ->findOrFail($sublote->id);
            $retorno = RetornoPacking::query()
                ->lockForUpdate()
                ->findOrFail($sublote->retorno_packing_id);
            $entrega = EntregaFrutaProceso::query()
                ->lockForUpdate()
                ->findOrFail($retorno->entrega_fruta_proceso_id);
            $lote = $this->loteActivo($entrega->lote_materia_prima_id);

            if ($sublote->operacion_ubicacion_id !== null) {
                if ($sublote->operacion_ubicacion_id !== $operacionId) {
                    throw new ConflictoOperacion('El sublote ya fue ubicado con otra operación.');
                }

                return $this->cargarLote($lote);
            }
            $this->asegurarOperacionDisponible($operacionId);
            if ($retorno->anulado_at !== null
                || $sublote->estado !== EstadoSubloteRetornoPacking::PendienteUbicacion) {
                throw new ConflictoOperacion('El sublote ya no se encuentra pendiente de ubicación.');
            }

            $camara = Camara::query()
                ->where('contenido', ContenidoCamara::MateriaPrima->value)
                ->where('estado', EstadoCamara::Activa->value)
                ->lockForUpdate()
                ->find($camaraId);
            if (! $camara) {
                throw ValidationException::withMessages([
                    'camara_id' => 'Selecciona una cámara activa exclusiva de materia prima.',
                ]);
            }

            $token = $usuario->currentAccessToken();
            $sublote->update([
                'estado' => EstadoSubloteRetornoPacking::UbicadoCamara,
                'camara_id' => $camara->id,
                'operacion_ubicacion_id' => $operacionId,
                'ubicado_por_user_id' => $usuario->id,
                'dispositivo_ubicacion_id' => $token instanceof PersonalAccessToken
                    ? $token->dispositivo_id
                    : null,
                'ubicado_at' => now(),
                'observacion_ubicacion' => filled($observacion)
                    ? trim((string) $observacion)
                    : null,
            ]);
            $this->registrarEvento(
                $lote,
                'sublote_packing_ubicado',
                $usuario,
                $operacionId,
                [
                    'retorno_id' => $retorno->id,
                    'sublote_id' => $sublote->id,
                    'numero_sublote' => $sublote->numero_sublote,
                    'camara_id' => $camara->id,
                    'camara_codigo' => $camara->codigo,
                ],
            );

            return $this->cargarLote($lote);
        }, attempts: 3);
    }

    public function anular(
        RetornoPacking $retorno,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): LoteMateriaPrima {
        return DB::transaction(function () use (
            $retorno,
            $operacionId,
            $motivo,
            $usuario,
        ): LoteMateriaPrima {
            $retorno = RetornoPacking::query()
                ->lockForUpdate()
                ->findOrFail($retorno->id);
            $entrega = EntregaFrutaProceso::query()
                ->lockForUpdate()
                ->findOrFail($retorno->entrega_fruta_proceso_id);
            $lote = $this->loteActivo($entrega->lote_materia_prima_id);

            if ($retorno->anulado_at !== null) {
                if ($retorno->operacion_anulacion_id !== $operacionId) {
                    throw new ConflictoOperacion('El retorno ya fue anulado con otra operación.');
                }

                return $this->cargarLote($lote);
            }
            $this->asegurarOperacionDisponible($operacionId);
            if (! $this->puedeAnular($retorno, $usuario)) {
                abort(403, 'No puedes anular este retorno o uno de sus sublotes ya fue ubicado.');
            }

            $retorno->update([
                'operacion_anulacion_id' => $operacionId,
                'anulado_por_user_id' => $usuario->id,
                'anulado_at' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);
            $retorno->resultados()->update([
                'estado' => EstadoSubloteRetornoPacking::Anulado->value,
                'updated_at' => now(),
            ]);
            $this->registrarEvento(
                $lote,
                'retorno_packing_anulado',
                $usuario,
                $operacionId,
                [
                    'retorno_id' => $retorno->id,
                    'numero' => $retorno->numero,
                    'entrega_id' => $entrega->id,
                    'motivo' => trim($motivo),
                ],
            );

            return $this->cargarLote($lote);
        }, attempts: 3);
    }

    public function puedeRegistrar(EntregaFrutaProceso $entrega, User $usuario): bool
    {
        return $entrega->anulado_at === null
            && $this->alcance->puedeEntregarFrutaProceso($usuario)
            && ! $entrega->retornos
                ->whereNull('anulado_at')
                ->contains(fn (RetornoPacking $retorno): bool => $retorno->cierra_entrega);
    }

    public function puedeUbicar(SubloteRetornoPacking $sublote, User $usuario): bool
    {
        return $this->alcance->puedeEntregarFrutaProceso($usuario)
            && $sublote->estado === EstadoSubloteRetornoPacking::PendienteUbicacion
            && $sublote->retorno?->anulado_at === null;
    }

    public function puedeAnular(
        RetornoPacking $retorno,
        User $usuario,
        ?string $ultimoRetornoVigenteId = null,
    ): bool {
        if ($retorno->anulado_at !== null
            || $retorno->resultados->contains(
                fn (SubloteRetornoPacking $sublote): bool => (
                    $sublote->estado === EstadoSubloteRetornoPacking::UbicadoCamara
                ),
            )) {
            return false;
        }
        if ($this->alcance->puedeCorregirEntregasFrutaProceso($usuario)) {
            return true;
        }
        if (! $this->alcance->puedeEntregarFrutaProceso($usuario)
            || $retorno->registrado_por_user_id !== $usuario->id) {
            return false;
        }

        $ultimoRetornoVigenteId ??= RetornoPacking::query()
            ->where('entrega_fruta_proceso_id', $retorno->entrega_fruta_proceso_id)
            ->whereNull('anulado_at')
            ->latest('registrado_at')
            ->latest('created_at')
            ->latest('id')
            ->value('id');

        return $ultimoRetornoVigenteId === $retorno->id;
    }

    public function entregaTieneRetornos(EntregaFrutaProceso $entrega): bool
    {
        return $entrega->retornos()->whereNull('anulado_at')->exists();
    }

    /** @param array<int, array<string, mixed>> $resultados */
    private function tiposResultado(array $resultados): Collection
    {
        $ids = collect($resultados)
            ->pluck('tipo_resultado_packing_id')
            ->unique()
            ->values();
        $tipos = TipoResultadoPacking::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($tipos->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'resultados' => 'Uno de los tipos de resultado ya no está disponible.',
            ]);
        }

        return $tipos;
    }

    /** @param array<string, mixed> $resultado */
    private function nombreResultado(
        TipoResultadoPacking $tipo,
        array $resultado,
    ): string {
        $personalizado = filled($resultado['nombre_resultado'] ?? null)
            ? trim((string) $resultado['nombre_resultado'])
            : null;
        if ($tipo->codigo === 'otro' && $personalizado === null) {
            throw ValidationException::withMessages([
                'resultados' => 'Especifica el nombre cuando el resultado es Otro.',
            ]);
        }

        return $personalizado ?? $tipo->nombre;
    }

    private function resolverRetornoRepetido(
        RetornoPacking $retorno,
        EntregaFrutaProceso $entrega,
        string $hash,
    ): LoteMateriaPrima {
        if ($retorno->entrega_fruta_proceso_id !== $entrega->id
            || ! hash_equals($retorno->payload_hash, $hash)) {
            throw new ConflictoOperacion(
                'El identificador de retorno ya fue utilizado con datos diferentes.',
            );
        }

        return $this->cargarLote($retorno->entrega->lote);
    }

    private function asegurarOperacionDisponible(string $operacionId): void
    {
        if (EventoLoteMateriaPrima::query()
            ->where('operacion_id', $operacionId)
            ->lockForUpdate()
            ->exists()) {
            throw new ConflictoOperacion(
                'El identificador ya fue utilizado con otra operación de materia prima.',
            );
        }
    }

    private function loteActivo(string $loteId): LoteMateriaPrima
    {
        return LoteMateriaPrima::query()
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->lockForUpdate()
            ->findOrFail($loteId);
    }

    /** @param array<string, mixed> $datos */
    private function payload(array $datos): array
    {
        return [
            'cierra_entrega' => (bool) $datos['cierra_entrega'],
            'observacion' => filled($datos['observacion'] ?? null)
                ? trim((string) $datos['observacion'])
                : null,
            'resultados' => collect($datos['resultados'])
                ->map(fn (array $resultado): array => [
                    'tipo_resultado_packing_id' => $resultado['tipo_resultado_packing_id'],
                    'nombre_resultado' => filled($resultado['nombre_resultado'] ?? null)
                        ? trim((string) $resultado['nombre_resultado'])
                        : null,
                    'cantidad_bins' => (int) $resultado['cantidad_bins'],
                    'kilos_netos' => filled($resultado['kilos_netos'] ?? null)
                        ? round((float) $resultado['kilos_netos'], 3)
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'payload' => 'El retorno contiene datos que no pueden procesarse.',
            ]);
        }
    }

    /** @param array<string, mixed> $datos */
    private function registrarEvento(
        LoteMateriaPrima $lote,
        string $tipo,
        User $usuario,
        string $operacionId,
        array $datos,
    ): void {
        EventoLoteMateriaPrima::create([
            'lote_materia_prima_id' => $lote->id,
            'operacion_id' => $operacionId,
            'tipo' => $tipo,
            'estado_anterior' => $lote->estado->value,
            'estado_nuevo' => $lote->estado->value,
            'user_id' => $usuario->id,
            'ocurrido_at' => now(),
            'datos' => $datos,
        ]);
    }

    private function cargarLote(LoteMateriaPrima $lote): LoteMateriaPrima
    {
        return $lote->fresh([
            'temporada',
            'cliente',
            'recepcion',
            'asignacionCamara.camara',
            'entregasProceso.entregadoPor',
            'entregasProceso.anuladoPor',
            'entregasProceso.dispositivo',
            'entregasProceso.retornos.registradoPor',
            'entregasProceso.retornos.anuladoPor',
            'entregasProceso.retornos.dispositivo',
            'entregasProceso.retornos.resultados.tipoResultado',
            'entregasProceso.retornos.resultados.camara',
            'entregasProceso.retornos.resultados.ubicadoPor',
            'entregasProceso.retornos.resultados.dispositivoUbicacion',
        ]);
    }
}
