<?php

namespace App\Services\Autenticacion;

use App\Models\Dispositivo;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class ContextoOperacional
{
    /**
     * @return array{User, Dispositivo}
     */
    public function obtener(Request $request): array
    {
        $usuario = $request->user();
        $token = $usuario?->currentAccessToken();

        if (! $usuario instanceof User
            || ! $token instanceof PersonalAccessToken
            || $token->dispositivo_id === null) {
            throw new AuthenticationException(
                'La solicitud requiere un token asociado a una tablet.',
            );
        }

        $dispositivo = $token->dispositivo()
            ->where('activo', true)
            ->first();

        if (! $dispositivo) {
            throw new AuthenticationException(
                'La tablet asociada al token no se encuentra activa.',
            );
        }

        return [$usuario, $dispositivo];
    }
}
