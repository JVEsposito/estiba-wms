<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReiniciarOperacionTemporadaRequest;
use App\Models\ReinicioOperacional;
use App\Models\Temporada;
use App\Services\Temporadas\ServicioReinicioOperacional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReinicioOperacionalController extends Controller
{
    public function preview(
        Request $request,
        Temporada $temporada,
        ServicioReinicioOperacional $servicio,
    ): JsonResponse {
        Gate::authorize('reiniciar-datos-operacionales');

        return response()->json([
            'data' => $servicio->previsualizar($temporada),
        ]);
    }

    public function store(
        ReiniciarOperacionTemporadaRequest $request,
        Temporada $temporada,
        ServicioReinicioOperacional $servicio,
    ): JsonResponse {
        if (! Hash::check(
            (string) $request->validated('password'),
            (string) $request->user()->password,
        )) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña del administrador no es correcta.'],
            ]);
        }

        $resultado = $servicio->ejecutar(
            $temporada,
            $request->validated('operacion_id'),
            $request->validated('motivo'),
            $request->user(),
        );

        return response()->json([
            'data' => $this->reinicio($resultado['reinicio']),
            'reutilizado' => $resultado['reutilizado'],
        ], $resultado['reutilizado']
            ? Response::HTTP_OK
            : Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function reinicio(ReinicioOperacional $reinicio): array
    {
        return [
            'id' => $reinicio->id,
            'operacion_id' => $reinicio->operacion_id,
            'temporada_id' => $reinicio->temporada_id,
            'alcances' => $reinicio->alcances,
            'motivo' => $reinicio->motivo,
            'resumen_antes' => $reinicio->resumen_antes,
            'resumen_eliminado' => $reinicio->resumen_eliminado,
            'resumen_despues' => $reinicio->resumen_despues,
            'ejecutado_por_user_id' => $reinicio->ejecutado_por_user_id,
            'created_at' => $reinicio->created_at?->toAtomString(),
        ];
    }
}
