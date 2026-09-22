<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExigirCambioPasswordTablet
{
    public function handle(Request $request, Closure $next): Response
    {
        // Las credenciales iniciales solo habilitan el cambio de clave y cerrar sesión.
        if (! $request->bearerToken()
            || $request->is('api/usuario/password')
            || ($request->is('api/acceso-tablet') && $request->isMethod('DELETE'))) {
            return $next($request);
        }

        $usuario = Auth::guard('sanctum')->user();
        $token = $usuario?->currentAccessToken();

        if ($usuario?->debe_cambiar_password
            && $token instanceof PersonalAccessToken
            && $token->dispositivo_id !== null) {
            return response()->json([
                'message' => 'Cambia tu contraseña temporal para continuar.',
                'codigo' => 'cambio_password_requerido',
            ], 403);
        }

        return $next($request);
    }
}
