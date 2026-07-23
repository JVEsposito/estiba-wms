<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorregirItemFolioMaterialRequest;
use App\Models\CorreccionItemFolioMaterial;
use App\Models\FolioMaterial;
use App\Services\Materiales\ServicioCorreccionItemMaterial;
use Illuminate\Http\JsonResponse;

class CorreccionItemMaterialController extends Controller
{
    public function store(
        CorregirItemFolioMaterialRequest $request,
        FolioMaterial $folioMaterial,
        ServicioCorreccionItemMaterial $servicio,
    ): JsonResponse {
        $correccion = $servicio->corregir(
            $folioMaterial,
            $request->validated('operacion_id'),
            $request->validated('item_material_id'),
            $request->validated('motivo'),
            $request->user(),
        );

        return response()->json(['data' => $this->serializar($correccion)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(CorreccionItemFolioMaterial $correccion): array
    {
        return [
            'id' => $correccion->id,
            'operacion_id' => $correccion->operacion_id,
            'folio' => [
                'id' => $correccion->folio_id,
                'numero_folio' => $correccion->folioMaterial->folio->numero_folio,
            ],
            'item_anterior' => [
                'id' => $correccion->itemAnterior->id,
                'codigo' => $correccion->itemAnterior->codigo,
                'nombre' => $correccion->itemAnterior->nombre,
            ],
            'item_nuevo' => [
                'id' => $correccion->itemNuevo->id,
                'codigo' => $correccion->itemNuevo->codigo,
                'nombre' => $correccion->itemNuevo->nombre,
            ],
            'cliente' => [
                'id' => $correccion->itemNuevo->cliente->id,
                'codigo' => $correccion->itemNuevo->cliente->codigo,
                'nombre' => $correccion->itemNuevo->cliente->nombre,
            ],
            'cantidad' => $correccion->cantidad,
            'motivo' => $correccion->motivo,
            'usuario' => [
                'id' => $correccion->usuario->id,
                'nombre' => $correccion->usuario->name,
            ],
            'ocurrido_at' => $correccion->ocurrido_at?->toAtomString(),
        ];
    }
}
