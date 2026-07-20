<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTunelPrefrioRequest;
use App\Http\Resources\TunelPrefrioResource;
use App\Models\TunelPrefrio;
use App\Services\Prefrio\ServicioConfiguracionTunelPrefrio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TunelPrefrioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('consultar-prefrio'), 403);

        $tuneles = TunelPrefrio::query()
            ->with([
                'posiciones' => fn ($consulta) => $consulta->orderBy('numero'),
                'procesoActivo.folios',
                'creadoPor:id,name',
            ])
            ->orderBy('codigo')
            ->get();

        return TunelPrefrioResource::collection($tuneles);
    }

    public function show(Request $request, TunelPrefrio $tunelPrefrio): TunelPrefrioResource
    {
        abort_unless($request->user()?->can('consultar-prefrio'), 403);

        return new TunelPrefrioResource($tunelPrefrio->load([
            'posiciones' => fn ($consulta) => $consulta->orderBy('numero'),
            'procesoActivo.folios',
            'creadoPor:id,name',
        ]));
    }

    public function siguienteCodigo(
        Request $request,
        ServicioConfiguracionTunelPrefrio $servicio,
    ): JsonResponse {
        abort_unless($request->user()?->can('administrar-tuneles-prefrio'), 403);

        return response()->json([
            'data' => ['codigo' => $servicio->siguienteCodigo()],
        ]);
    }

    public function store(
        GuardarTunelPrefrioRequest $request,
        ServicioConfiguracionTunelPrefrio $servicio,
    ): Response {
        $tunel = $servicio->crear($request->validated(), $request->user());

        return (new TunelPrefrioResource($tunel))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        GuardarTunelPrefrioRequest $request,
        TunelPrefrio $tunelPrefrio,
        ServicioConfiguracionTunelPrefrio $servicio,
    ): TunelPrefrioResource {
        return new TunelPrefrioResource(
            $servicio->actualizar($tunelPrefrio, $request->validated(), $request->user()),
        );
    }
}
