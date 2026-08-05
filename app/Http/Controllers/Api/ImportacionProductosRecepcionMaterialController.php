<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Materiales\ServicioPrevisualizacionImportacionRecepcionMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportacionProductosRecepcionMaterialController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioPrevisualizacionImportacionRecepcionMaterial $servicio,
    ): JsonResponse {
        Gate::authorize('gestionar-recepciones-materiales');

        $datos = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'proveedor_material_id' => ['required', 'uuid', 'exists:proveedores_materiales,id'],
        ]);

        return response()->json([
            'data' => $servicio->previsualizar(
                $request->file('archivo'),
                $datos['cliente_id'],
                $datos['proveedor_material_id'],
            ),
        ]);
    }
}
