<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarClienteGlobalRequest;
use App\Models\Cliente;
use App\Services\Clientes\ServicioCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ClienteGlobalController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('administrar-accesos');

        return response()->json([
            'data' => Cliente::query()
                ->with([
                    'aliases' => fn ($consulta) => $consulta->orderByDesc('created_at'),
                ])
                ->withCount(['catalogosMateriales', 'catalogosValidacion'])
                ->orderByDesc('activo')
                ->orderBy('codigo')
                ->get()
                ->map(fn (Cliente $cliente): array => $this->cliente($cliente)),
        ]);
    }

    public function store(
        GuardarClienteGlobalRequest $request,
        ServicioCliente $servicio,
    ): JsonResponse {
        $cliente = $servicio->guardarMaestro(
            $request->validated(),
            $request->user(),
        );

        return response()->json(
            ['data' => $this->cliente($cliente)],
            Response::HTTP_CREATED,
        );
    }

    public function update(
        GuardarClienteGlobalRequest $request,
        Cliente $cliente,
        ServicioCliente $servicio,
    ): JsonResponse {
        $cliente = $servicio->guardarMaestro(
            $request->validated(),
            $request->user(),
            $cliente,
        );

        return response()->json(['data' => $this->cliente($cliente)]);
    }

    /** @return array<string, mixed> */
    private function cliente(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'codigo' => $cliente->codigo,
            'nombre' => $cliente->nombre,
            'codigo_externo' => $cliente->codigo_externo,
            'codigo_folio_materiales' => $cliente->codigo_folio_materiales,
            'activo' => $cliente->activo,
            'aliases' => $cliente->relationLoaded('aliases')
                ? $cliente->aliases->map(fn ($alias): array => [
                    'id' => $alias->id,
                    'origen' => $alias->origen,
                    'codigo' => $alias->codigo,
                    'nombre' => $alias->nombre,
                ])->values()
                : [],
            'presencias' => [
                'materiales' => (int) ($cliente->catalogos_materiales_count
                    ?? $cliente->catalogosMateriales()->count()),
                'validacion' => (int) ($cliente->catalogos_validacion_count
                    ?? $cliente->catalogosValidacion()->count()),
            ],
            'created_at' => $cliente->created_at?->toAtomString(),
            'updated_at' => $cliente->updated_at?->toAtomString(),
        ];
    }
}
