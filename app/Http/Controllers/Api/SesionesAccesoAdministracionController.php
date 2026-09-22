<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoSesionEstiba;
use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\SesionEstiba;
use App\Models\User;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SesionesAccesoAdministracionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('administrar-accesos');

        $sesiones = $this->vigentes()
            ->with(['tokenable:id,name,email', 'dispositivo:id,codigo,nombre'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);
        $tokenActual = $request->user()->currentAccessToken();

        return response()->json([
            'data' => $sesiones->getCollection()->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'usuario' => [
                    'id' => $token->tokenable->id,
                    'nombre' => $token->tokenable->name,
                    'email' => $token->tokenable->email,
                ],
                'tipo' => $token->dispositivo_id ? 'tablet' : 'oficina',
                'dispositivo' => $token->dispositivo ? [
                    'codigo' => $token->dispositivo->codigo,
                    'nombre' => $token->dispositivo->nombre,
                ] : null,
                'creada_at' => $token->created_at?->toAtomString(),
                'ultima_actividad_at' => $token->last_used_at?->toAtomString(),
                'es_actual' => $tokenActual instanceof PersonalAccessToken
                    && $tokenActual->getKey() === $token->getKey(),
            ])->values(),
            'pagina' => $sesiones->currentPage(),
            'ultima_pagina' => $sesiones->lastPage(),
            'total' => $sesiones->total(),
        ]);
    }

    public function destroy(
        Request $request,
        int $sesionAcceso,
        ServicioSesionEstiba $servicioSesiones,
    ): JsonResponse {
        Gate::authorize('administrar-accesos');

        $resultado = DB::transaction(function () use ($request, $sesionAcceso, $servicioSesiones): array {
            $token = $this->vigentes()->lockForUpdate()->findOrFail($sesionAcceso);
            $usuario = $token->tokenable;
            $dispositivo = $token->dispositivo;
            $cerradas = 0;

            if ($dispositivo) {
                $sesiones = SesionEstiba::query()
                    ->where('user_id', $usuario->id)
                    ->where('dispositivo_id', $dispositivo->id)
                    ->where('estado', EstadoSesionEstiba::Abierta->value)
                    ->get();

                foreach ($sesiones as $sesion) {
                    $servicioSesiones->cerrarForzosamente(
                        $sesion,
                        $request->user(),
                        'Acceso de tablet revocado desde Accesos y temporadas.',
                        desdeAdministracion: true,
                    );
                    $cerradas++;
                }
            }

            DB::table('auditoria_cierres_acceso')->insert([
                'administrador_user_id' => $request->user()->id,
                'usuario_user_id' => $usuario->id,
                'token_id' => $token->id,
                'token_nombre' => $token->name,
                'dispositivo_codigo' => $dispositivo?->codigo,
                'sesiones_camara_cerradas' => $cerradas,
                'created_at' => now(),
            ]);

            $esActual = $request->user()->currentAccessToken() instanceof PersonalAccessToken
                && $request->user()->currentAccessToken()->getKey() === $token->getKey();
            $token->delete();

            return ['sesion_actual_cerrada' => $esActual, 'sesiones_camara_cerradas' => $cerradas];
        }, attempts: 3);

        return response()->json($resultado);
    }

    private function vigentes(): Builder
    {
        $consulta = PersonalAccessToken::query()
            ->whereHasMorph('tokenable', [User::class], fn (Builder $query) => $query->where('activo', true))
            ->where(fn (Builder $query) => $query->whereNull('dispositivo_id')
                ->orWhereHas('dispositivo', fn (Builder $dispositivo) => $dispositivo->where('activo', true)))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));

        if (config('sanctum.expiration') !== null) {
            $consulta->where('created_at', '>', now()->subMinutes((int) config('sanctum.expiration')));
        }

        return $consulta;
    }
}
