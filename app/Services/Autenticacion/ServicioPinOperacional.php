<?php

namespace App\Services\Autenticacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * PIN personal de 4 dígitos que confirma, desde la tablet, que quien ejecuta
 * una acción física es el usuario de la sesión. No reemplaza la contraseña.
 */
class ServicioPinOperacional
{
    public const INTENTOS_MAXIMOS = 5;

    public const BLOQUEO_MINUTOS = 5;

    /**
     * @return array{configurado: bool, bloqueado_hasta: ?string}
     */
    public function estado(User $usuario): array
    {
        $bloqueo = $usuario->pin_operacional_bloqueado_hasta;

        return [
            'configurado' => $usuario->pin_operacional_hash !== null,
            'bloqueado_hasta' => $bloqueo?->isFuture() ? $bloqueo->toAtomString() : null,
        ];
    }

    public function configurar(User $usuario, string $pin, ?string $pinActual): void
    {
        $this->validarFormato($pin);

        if ($usuario->pin_operacional_hash !== null) {
            $this->verificar($usuario, (string) $pinActual, 'pin_actual');
        }

        $usuario->forceFill([
            'pin_operacional_hash' => Hash::make($pin),
            'pin_operacional_actualizado_at' => now(),
            'pin_operacional_intentos_fallidos' => 0,
            'pin_operacional_bloqueado_hasta' => null,
        ])->save();
    }

    /**
     * Verifica el PIN. Los intentos fallidos se guardan antes de rechazar,
     * para que el bloqueo sobreviva al error de la solicitud.
     */
    public function verificar(User $usuario, string $pin, string $campo = 'pin'): void
    {
        $error = DB::transaction(function () use ($usuario, $pin): ?string {
            $bloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);

            if ($bloqueado->pin_operacional_hash === null) {
                return 'Crea tu PIN operacional antes de confirmar.';
            }

            if ($bloqueado->pin_operacional_bloqueado_hasta?->isFuture()) {
                return $this->mensajeBloqueo($bloqueado);
            }

            if (Hash::check($pin, $bloqueado->pin_operacional_hash)) {
                if ($bloqueado->pin_operacional_intentos_fallidos > 0) {
                    $bloqueado->forceFill(['pin_operacional_intentos_fallidos' => 0])->save();
                }

                return null;
            }

            $intentos = $bloqueado->pin_operacional_intentos_fallidos + 1;

            if ($intentos >= self::INTENTOS_MAXIMOS) {
                $bloqueado->forceFill([
                    'pin_operacional_intentos_fallidos' => 0,
                    'pin_operacional_bloqueado_hasta' => now()->addMinutes(self::BLOQUEO_MINUTOS),
                ])->save();

                return $this->mensajeBloqueo($bloqueado);
            }

            $bloqueado->forceFill(['pin_operacional_intentos_fallidos' => $intentos])->save();
            $restantes = self::INTENTOS_MAXIMOS - $intentos;

            return $restantes === 1
                ? 'PIN incorrecto. Te queda 1 intento antes del bloqueo.'
                : "PIN incorrecto. Te quedan {$restantes} intentos antes del bloqueo.";
        }, attempts: 3);

        $usuario->refresh();

        if ($error !== null) {
            throw ValidationException::withMessages([$campo => $error]);
        }
    }

    public function restablecer(User $usuario): void
    {
        $usuario->forceFill([
            'pin_operacional_hash' => null,
            'pin_operacional_actualizado_at' => now(),
            'pin_operacional_intentos_fallidos' => 0,
            'pin_operacional_bloqueado_hasta' => null,
        ])->save();
    }

    private function validarFormato(string $pin): void
    {
        if (preg_match('/^\d{4}$/', $pin) !== 1) {
            throw ValidationException::withMessages(['pin' => 'El PIN debe tener exactamente 4 dígitos.']);
        }

        $digitos = array_map('intval', str_split($pin));
        $diferencias = array_unique(array_map(
            fn (int $indice): int => $digitos[$indice + 1] - $digitos[$indice],
            [0, 1, 2],
        ));

        // Rechaza 0000, 1111, 1234, 4321 y similares: son los primeros que se prueban.
        if (count($diferencias) === 1 && in_array(reset($diferencias), [-1, 0, 1], true)) {
            throw ValidationException::withMessages([
                'pin' => 'Elige un PIN menos predecible: no repitas ni sigas dígitos consecutivos.',
            ]);
        }
    }

    private function mensajeBloqueo(User $usuario): string
    {
        $hora = $usuario->pin_operacional_bloqueado_hasta
            ?->timezone(config('app.operational_timezone', config('app.timezone')))
            ->format('H:i');

        return "PIN bloqueado por intentos fallidos hasta las {$hora}. Un administrador puede restablecerlo desde Accesos.";
    }
}
