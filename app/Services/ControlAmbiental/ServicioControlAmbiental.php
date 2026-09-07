<?php

namespace App\Services\ControlAmbiental;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\CorreccionControlAmbiental;
use App\Models\Dispositivo;
use App\Models\RegistroControlAmbiental;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioControlAmbiental
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array{registro: RegistroControlAmbiental, creado: bool}
     */
    public function registrar(
        Camara $camara,
        array $datos,
        User $usuario,
        Dispositivo $dispositivo,
    ): array {
        $capturadoAt = CarbonImmutable::parse($datos['capturado_at'])
            ->utc()
            ->startOfSecond();
        $payload = [
            'camara_id' => (string) $camara->id,
            ...$this->temperaturasNormalizadas($datos),
            'capturado_at' => $capturadoAt->toIso8601String(),
        ];
        $payloadHash = $this->hash($payload);

        return DB::transaction(function () use (
            $camara,
            $datos,
            $usuario,
            $dispositivo,
            $capturadoAt,
            $payload,
            $payloadHash,
        ): array {
            $camaraBloqueada = Camara::query()
                ->whereKey($camara->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existente = RegistroControlAmbiental::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->camara_id !== $camara->id
                    || $existente->registrado_por_user_id !== $usuario->id
                    || $existente->dispositivo_id !== $dispositivo->id
                    || ! hash_equals($existente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El UUID del control ambiental ya fue utilizado con datos diferentes.',
                    );
                }

                return [
                    'registro' => $this->cargar($existente),
                    'creado' => false,
                ];
            }

            $this->asegurarCamaraControlable($camaraBloqueada);

            $inicioConflicto = $capturadoAt->subMinutes(
                RegistroControlAmbiental::FRECUENCIA_MINUTOS,
            );
            $finConflicto = $capturadoAt->addMinutes(
                RegistroControlAmbiental::FRECUENCIA_MINUTOS,
            );
            $duplicado = RegistroControlAmbiental::query()
                ->where('camara_id', $camaraBloqueada->id)
                ->where('capturado_at', '>', $inicioConflicto)
                ->where('capturado_at', '<', $finConflicto)
                ->orderByDesc('capturado_at')
                ->lockForUpdate()
                ->first();

            if ($duplicado) {
                throw new ConflictoOperacion(sprintf(
                    'La cámara ya posee un control dentro de la ventana de %d minutos.',
                    RegistroControlAmbiental::FRECUENCIA_MINUTOS,
                ));
            }

            $registro = RegistroControlAmbiental::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $payloadHash,
                'camara_id' => $camaraBloqueada->id,
                'registrado_por_user_id' => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                ...$payload,
                'capturado_at' => $capturadoAt,
                'recibido_servidor_at' => now(),
                'version' => 0,
            ]);

            return [
                'registro' => $this->cargar($registro),
                'creado' => true,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function corregir(
        RegistroControlAmbiental $registro,
        array $datos,
        User $usuario,
    ): RegistroControlAmbiental {
        $temperaturas = $this->temperaturasNormalizadas($datos);
        $payload = [
            'registro_control_ambiental_id' => (string) $registro->id,
            'version' => (int) $datos['version'],
            ...$temperaturas,
            'motivo' => trim($datos['motivo']),
        ];
        $payloadHash = $this->hash($payload);

        return DB::transaction(function () use (
            $registro,
            $datos,
            $usuario,
            $temperaturas,
            $payload,
            $payloadHash,
        ): RegistroControlAmbiental {
            $existente = CorreccionControlAmbiental::query()
                ->where('operacion_id', $datos['operacion_id'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->registro_control_ambiental_id !== $registro->id
                    || $existente->corregido_por_user_id !== $usuario->id
                    || ! hash_equals($existente->payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El UUID de la corrección ambiental ya fue utilizado con datos diferentes.',
                    );
                }

                return $this->cargar(
                    RegistroControlAmbiental::query()->findOrFail($registro->id),
                );
            }

            $registroBloqueado = RegistroControlAmbiental::query()
                ->whereKey($registro->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registroBloqueado->version !== (int) $datos['version']) {
                throw new ConflictoOperacion(
                    'El control ambiental cambió desde la última lectura. Actualiza antes de corregir.',
                );
            }

            $anteriores = $this->snapshotTemperaturas($registroBloqueado);
            $nuevos = [
                ...$temperaturas,
                'version' => $registroBloqueado->version + 1,
            ];

            if ($this->mismasTemperaturas($anteriores, $nuevos)) {
                throw new DomainException(
                    'La corrección debe modificar al menos una temperatura.',
                );
            }

            $registroBloqueado->forceFill([
                ...$temperaturas,
                'version' => $registroBloqueado->version + 1,
            ])->save();

            CorreccionControlAmbiental::create([
                'operacion_id' => $datos['operacion_id'],
                'payload_hash' => $payloadHash,
                'registro_control_ambiental_id' => $registroBloqueado->id,
                'corregido_por_user_id' => $usuario->id,
                'datos_anteriores' => $anteriores,
                'datos_nuevos' => $nuevos,
                'motivo' => $payload['motivo'],
                'corregido_at' => now(),
            ]);

            return $this->cargar($registroBloqueado);
        });
    }

    private function asegurarCamaraControlable(Camara $camara): void
    {
        if ($camara->contenido !== ContenidoCamara::Productos) {
            throw new DomainException(
                'El control ambiental horario solo aplica a cámaras de producto terminado.',
            );
        }

        if ($camara->estado !== EstadoCamara::Activa) {
            throw new DomainException(
                'No se puede registrar un control ambiental en una cámara inactiva.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{temperatura_inicio_c: float, temperatura_medio_c: float, temperatura_fondo_c: float}
     */
    private function temperaturasNormalizadas(array $datos): array
    {
        return [
            'temperatura_inicio_c' => round((float) $datos['temperatura_inicio_c'], 2),
            'temperatura_medio_c' => round((float) $datos['temperatura_medio_c'], 2),
            'temperatura_fondo_c' => round((float) $datos['temperatura_fondo_c'], 2),
        ];
    }

    /**
     * @return array{temperatura_inicio_c: float, temperatura_medio_c: float, temperatura_fondo_c: float, version: int}
     */
    private function snapshotTemperaturas(RegistroControlAmbiental $registro): array
    {
        return [
            'temperatura_inicio_c' => (float) $registro->temperatura_inicio_c,
            'temperatura_medio_c' => (float) $registro->temperatura_medio_c,
            'temperatura_fondo_c' => (float) $registro->temperatura_fondo_c,
            'version' => $registro->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $anteriores
     * @param  array<string, mixed>  $nuevos
     */
    private function mismasTemperaturas(array $anteriores, array $nuevos): bool
    {
        foreach (['temperatura_inicio_c', 'temperatura_medio_c', 'temperatura_fondo_c'] as $campo) {
            if (round((float) $anteriores[$campo], 2) !== round((float) $nuevos[$campo], 2)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function cargar(RegistroControlAmbiental $registro): RegistroControlAmbiental
    {
        return $registro->fresh([
            'camara:id,codigo,nombre',
            'registradoPor:id,name',
            'dispositivo:id,codigo,nombre',
            'correcciones.corregidoPor:id,name',
        ]) ?? $registro;
    }
}
