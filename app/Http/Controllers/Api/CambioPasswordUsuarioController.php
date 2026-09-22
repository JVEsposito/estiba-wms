<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CambioPasswordUsuarioController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password_nueva' => [
                'required', 'string', 'confirmed', 'min:10',
                'regex:/^(?=.*\p{L})(?=.*\p{N})/u',
            ],
        ]);

        $token = $request->user()->currentAccessToken();

        DB::transaction(function () use ($request, $datos, $token): void {
            $usuario = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if (! Hash::check($datos['password_actual'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'password_actual' => 'La contraseña actual no es correcta.',
                ]);
            }

            if (Hash::check($datos['password_nueva'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'password_nueva' => 'La nueva contraseña debe ser distinta de la temporal.',
                ]);
            }

            $usuario->update([
                'password' => $datos['password_nueva'],
                'debe_cambiar_password' => false,
            ]);

            if ($token instanceof PersonalAccessToken) {
                $usuario->tokens()->where('id', '!=', $token->getKey())->delete();
            } else {
                $usuario->tokens()->delete();
            }
        }, attempts: 3);

        return response()->json(['debe_cambiar_password' => false]);
    }
}
