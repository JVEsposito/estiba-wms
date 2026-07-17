<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarValidacionPalletRequest;
use App\Models\ValidacionPallet;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Validacion\ServicioValidacionPallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidacionPalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consulta = ValidacionPallet::query()
            ->with(['folio:id,numero_folio,estado_operacional', 'usuario:id,name', 'dispositivo:id,codigo,nombre'])
            ->when($request->filled('folio'), fn ($q) => $q->where('numero_folio', mb_strtoupper(trim((string) $request->input('folio')))))
            ->latest('recibido_servidor_at');

        return response()->json($consulta->paginate($request->integer('por_pagina', 25)));
    }

    public function show(ValidacionPallet $validacionPallet): JsonResponse
    {
        return response()->json(['data' => $validacionPallet->load(['folio', 'usuario:id,name', 'dispositivo:id,codigo,nombre', 'conflictoCon'])]);
    }

    public function store(
        RegistrarValidacionPalletRequest $request,
        ContextoOperacional $contexto,
        ServicioValidacionPallet $servicio,
    ): JsonResponse {
        [$usuario, $dispositivo] = $contexto->obtener($request);
        [$validacion, $creada, $conflicto] = $servicio->registrar($request->validated(), $usuario, $dispositivo);

        $estado = $conflicto
            ? Response::HTTP_CONFLICT
            : ($creada ? Response::HTTP_CREATED : Response::HTTP_OK);

        return response()->json([
            'data' => $validacion,
            'catalogo_desactualizado' => $validacion->catalogo_version_dispositivo !== $validacion->catalogo_version_servidor,
            'message' => $conflicto
                ? 'El folio ya posee una aprobación o existe en inventario. La contradicción quedó auditada.'
                : null,
        ], $estado);
    }
}
