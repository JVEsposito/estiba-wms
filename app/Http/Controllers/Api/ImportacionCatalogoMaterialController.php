<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ImportacionCatalogoMaterialResource;
use App\Models\ImportacionCatalogoMaterial;
use App\Services\Materiales\ServicioImportacionCatalogoMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportacionCatalogoMaterialController extends Controller
{
    public function index(): JsonResponse
    {
        $importaciones = ImportacionCatalogoMaterial::query()
            ->with(['creadoPor:id,name', 'confirmadoPor:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(['data' => $importaciones->map(fn (ImportacionCatalogoMaterial $importacion): array => [
            'id' => $importacion->id,
            'nombre_archivo' => $importacion->nombre_archivo,
            'estado' => $importacion->estado,
            'resumen' => $importacion->resumen,
            'creado_por' => $importacion->creadoPor ? [
                'id' => $importacion->creadoPor->id,
                'nombre' => $importacion->creadoPor->name,
            ] : null,
            'confirmado_por' => $importacion->confirmadoPor ? [
                'id' => $importacion->confirmadoPor->id,
                'nombre' => $importacion->confirmadoPor->name,
            ] : null,
            'confirmado_at' => $importacion->confirmado_at?->toAtomString(),
            'created_at' => $importacion->created_at?->toAtomString(),
        ])]);
    }

    public function previsualizar(
        Request $request,
        ServicioImportacionCatalogoMaterial $servicio,
    ): JsonResponse {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
        ]);
        $importacion = $servicio->previsualizar($request->file('archivo'), $request->user());

        return (new ImportacionCatalogoMaterialResource($importacion))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function confirmar(
        Request $request,
        ImportacionCatalogoMaterial $importacionCatalogoMaterial,
        ServicioImportacionCatalogoMaterial $servicio,
    ): ImportacionCatalogoMaterialResource {
        return new ImportacionCatalogoMaterialResource(
            $servicio->confirmar($importacionCatalogoMaterial, $request->user()),
        );
    }
}
