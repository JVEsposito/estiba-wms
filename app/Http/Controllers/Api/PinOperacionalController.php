<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Autenticacion\ServicioPinOperacional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PinOperacionalController extends Controller
{
    public function show(Request $request, ServicioPinOperacional $pines): JsonResponse
    {
        return response()->json(['data' => $pines->estado($request->user())]);
    }

    public function update(Request $request, ServicioPinOperacional $pines): JsonResponse
    {
        $datos = $request->validate([
            'pin' => ['required', 'string', 'confirmed'],
            'pin_actual' => ['nullable', 'string', 'max:10'],
        ], [
            'pin.confirmed' => 'Los dos PIN ingresados no coinciden.',
        ]);

        $pines->configurar($request->user(), $datos['pin'], $datos['pin_actual'] ?? null);

        return response()->json(['data' => $pines->estado($request->user()->refresh())]);
    }

    public function restablecer(Request $request, User $usuario, ServicioPinOperacional $pines): JsonResponse
    {
        if (! $usuario->activo) {
            throw ValidationException::withMessages([
                'usuario' => 'Solo se puede restablecer el PIN de un usuario activo.',
            ]);
        }

        $pines->restablecer($usuario);

        return response()->json(['data' => $pines->estado($usuario->refresh())]);
    }
}
