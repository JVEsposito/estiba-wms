<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAndenRequest;
use App\Http\Resources\AndenResource;
use App\Models\Anden;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AndenController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-cargas-operacion');
        $incluirInactivos = $request->boolean('incluir_inactivos')
            && app(AlcanceOperacionalUsuario::class)->puedeGestionarAndenes($request->user());

        return AndenResource::collection(
            Anden::query()
                ->when(! $incluirInactivos, fn ($consulta) => $consulta->where('activo', true))
                ->orderBy('codigo')
                ->get(),
        );
    }

    public function store(GuardarAndenRequest $request): JsonResponse
    {
        $anden = Anden::create([
            ...$request->validated(),
            'activo' => $request->boolean('activo', true),
            'creado_por_user_id' => $request->user()->id,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return (new AndenResource($anden))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(GuardarAndenRequest $request, Anden $anden): AndenResource
    {
        $anden->update([
            ...$request->validated(),
            'activo' => $request->has('activo') ? $request->boolean('activo') : $anden->activo,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return new AndenResource($anden->refresh());
    }
}
