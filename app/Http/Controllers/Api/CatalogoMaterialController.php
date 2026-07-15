<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarDestinoMaterialRequest;
use App\Http\Requests\GuardarItemMaterialRequest;
use App\Http\Resources\DestinoMaterialResource;
use App\Http\Resources\ItemMaterialResource;
use App\Models\DestinoMaterial;
use App\Models\ItemMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CatalogoMaterialController extends Controller
{
    public function catalogo(): JsonResponse
    {
        Gate::authorize('consultar-materiales');

        return response()->json([
            'items' => ItemMaterialResource::collection(
                ItemMaterial::query()->where('activo', true)->orderBy('nombre')->get(),
            ),
            'destinos' => DestinoMaterialResource::collection(
                DestinoMaterial::query()->where('activo', true)->orderBy('nombre')->get(),
            ),
        ]);
    }

    public function items(): JsonResponse
    {
        Gate::authorize('administrar-catalogos-materiales');

        $items = ItemMaterial::query()
            ->withCount([
                'foliosMateriales as folios_activos_count' => fn ($consulta) => $consulta
                    ->whereHas('folio', fn ($folios) => $folios->where('activo', true)),
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => ItemMaterialResource::collection($items)]);
    }

    public function storeItem(GuardarItemMaterialRequest $request): JsonResponse
    {
        $item = ItemMaterial::create([
            ...$request->validated(),
            'activo' => true,
            'origen_sistema' => 'manual',
            'creado_por_user_id' => $request->user()->id,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return (new ItemMaterialResource($item))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateItem(
        GuardarItemMaterialRequest $request,
        ItemMaterial $itemMaterial,
    ): ItemMaterialResource {
        $datos = $request->validated();

        if ($itemMaterial->foliosMateriales()->exists()) {
            unset($datos['unidad_medida']);
        }

        $itemMaterial->update([
            ...$datos,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return new ItemMaterialResource($itemMaterial->refresh());
    }

    public function destinos(): JsonResponse
    {
        Gate::authorize('administrar-catalogos-materiales');

        return response()->json([
            'data' => DestinoMaterialResource::collection(
                DestinoMaterial::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            ),
        ]);
    }

    public function storeDestino(GuardarDestinoMaterialRequest $request): JsonResponse
    {
        $this->validarDestinoUnico($request);
        $destino = DestinoMaterial::create([
            ...$request->validated(),
            'activo' => true,
            'origen_sistema' => 'manual',
            'creado_por_user_id' => $request->user()->id,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return (new DestinoMaterialResource($destino))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateDestino(
        GuardarDestinoMaterialRequest $request,
        DestinoMaterial $destinoMaterial,
    ): DestinoMaterialResource {
        $this->validarDestinoUnico($request, $destinoMaterial);
        $destinoMaterial->update([
            ...$request->validated(),
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return new DestinoMaterialResource($destinoMaterial->refresh());
    }

    private function validarDestinoUnico(
        Request $request,
        ?DestinoMaterial $destino = null,
    ): void {
        $duplicado = DestinoMaterial::query()
            ->where('nombre', $request->string('nombre')->trim()->toString())
            ->where('centro_costo', $request->string('centro_costo')->trim()->upper()->toString())
            ->when($destino, fn ($consulta) => $consulta->where('id', '!=', $destino->id))
            ->exists();

        abort_if($duplicado, Response::HTTP_UNPROCESSABLE_ENTITY, 'El destino ya existe para ese centro de costo.');
    }
}
