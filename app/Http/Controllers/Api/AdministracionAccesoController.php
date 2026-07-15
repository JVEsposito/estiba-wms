<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrearDispositivoAdministracionRequest;
use App\Http\Requests\CrearUsuarioAdministracionRequest;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdministracionAccesoController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('administrar-accesos');

        return response()->json([
            'usuarios' => User::query()
                ->orderBy('name')
                ->orderBy('email')
                ->get()
                ->map(fn (User $usuario): array => $this->usuario($usuario)),
            'dispositivos' => Dispositivo::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Dispositivo $dispositivo): array => $this->dispositivo($dispositivo)),
        ]);
    }

    public function crearUsuario(CrearUsuarioAdministracionRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = User::create([
            'name' => $datos['nombre'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol' => $datos['rol'],
            'activo' => true,
        ]);

        return response()->json(
            ['usuario' => $this->usuario($usuario)],
            Response::HTTP_CREATED,
        );
    }

    public function crearDispositivo(CrearDispositivoAdministracionRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $dispositivo = Dispositivo::create([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'plataforma' => 'android',
            'activo' => true,
        ]);

        return response()->json(
            ['dispositivo' => $this->dispositivo($dispositivo)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function usuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'rol' => $usuario->rol->value,
            'activo' => $usuario->activo,
            'creado_at' => $usuario->created_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dispositivo(Dispositivo $dispositivo): array
    {
        return [
            'id' => $dispositivo->id,
            'codigo' => $dispositivo->codigo,
            'nombre' => $dispositivo->nombre,
            'plataforma' => $dispositivo->plataforma,
            'activo' => $dispositivo->activo,
            'ultimo_acceso_at' => $dispositivo->ultimo_acceso_at?->toAtomString(),
            'creado_at' => $dispositivo->created_at?->toAtomString(),
        ];
    }
}
