<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Autenticacion\ServicioPinOperacional;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
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

    /** Operadores de frío activos y el estado de su PIN, para el supervisor de turno. */
    public function operadores(ServicioPinOperacional $pines): JsonResponse
    {
        $operadores = User::query()
            ->where('activo', true)
            ->whereIn('rol', array_map(
                fn (RolUsuario $rol): string => $rol->value,
                AlcanceOperacionalUsuario::ROLES_PIN_SUPERVISADOS,
            ))
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario): array => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'rol' => $usuario->rol->value,
                'pin' => $pines->estado($usuario),
            ])
            ->values();

        return response()->json(['data' => $operadores]);
    }

    public function restablecer(Request $request, User $usuario, ServicioPinOperacional $pines): JsonResponse
    {
        if (! $usuario->activo) {
            throw ValidationException::withMessages([
                'usuario' => 'Solo se puede restablecer el PIN de un usuario activo.',
            ]);
        }

        $pines->restablecer($usuario, $request->user());

        return response()->json(['data' => $pines->estado($usuario->refresh())]);
    }
}
