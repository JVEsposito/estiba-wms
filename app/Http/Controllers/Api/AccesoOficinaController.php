<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccesoOficinaRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccesoOficinaController extends Controller
{
    public function store(AccesoOficinaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = User::query()
            ->where('email', mb_strtolower($datos['email']))
            ->first();

        if (! $usuario
            || ! $usuario->activo
            || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no son válidas.',
            ]);
        }

        if (! in_array($usuario->rol, [
            RolUsuario::Administrador,
            RolUsuario::Supervisor,
            RolUsuario::Despachador,
        ], true)) {
            throw ValidationException::withMessages([
                'email' => 'El usuario no posee acceso a los módulos de oficina.',
            ]);
        }

        $token = $usuario->createToken(
            'oficina-'.now()->format('Ymd-His'),
            ['oficina'],
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol->value,
                'puede_configurar_camaras' => in_array($usuario->rol, [
                    RolUsuario::Administrador,
                    RolUsuario::Supervisor,
                ], true),
                'puede_administrar_camaras' => $usuario->rol === RolUsuario::Administrador,
                'puede_administrar_accesos' => $usuario->rol === RolUsuario::Administrador,
                'puede_gestionar_cargas' => in_array($usuario->rol, [
                    RolUsuario::Administrador,
                    RolUsuario::Supervisor,
                    RolUsuario::Despachador,
                ], true),
            ],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
