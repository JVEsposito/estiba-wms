<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccesoTabletRequest;
use App\Models\Dispositivo;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccesoTabletController extends Controller
{
    public function store(AccesoTabletRequest $request): JsonResponse
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

        $dispositivo = Dispositivo::query()
            ->where('codigo', $datos['codigo_dispositivo'])
            ->where('activo', true)
            ->first();

        if (! $dispositivo) {
            throw ValidationException::withMessages([
                'codigo_dispositivo' => 'La tablet no está autorizada o se encuentra inactiva.',
            ]);
        }

        $nuevoToken = DB::transaction(function () use ($usuario, $dispositivo) {
            $usuario->tokens()
                ->where('dispositivo_id', $dispositivo->id)
                ->delete();

            $dispositivo->update(['ultimo_acceso_at' => now()]);

            return $usuario->crearTokenParaDispositivo(
                $dispositivo,
                "tablet-{$dispositivo->codigo}",
            );
        });

        return response()->json([
            'token' => $nuevoToken->plainTextToken,
            'token_type' => 'Bearer',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol->value,
            ],
            'dispositivo' => [
                'id' => $dispositivo->id,
                'codigo' => $dispositivo->codigo,
                'nombre' => $dispositivo->nombre,
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
