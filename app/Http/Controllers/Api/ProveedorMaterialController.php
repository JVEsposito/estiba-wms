<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarProveedorMaterialRequest;
use App\Models\ClienteProveedorMaterial;
use App\Models\ProveedorMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProveedorMaterialController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('administrar-catalogos-materiales');

        return response()->json([
            'data' => ProveedorMaterial::query()
                ->with([
                    'clientes' => fn ($consulta) => $consulta
                        ->wherePivot('activo', true)
                        ->orderBy('clientes.codigo'),
                ])
                ->orderByDesc('activo')
                ->orderBy('codigo')
                ->get()
                ->map(fn (ProveedorMaterial $proveedor): array => $this->proveedor($proveedor)),
        ]);
    }

    public function store(GuardarProveedorMaterialRequest $request): JsonResponse
    {
        $proveedor = $this->guardar($request);

        return response()->json(
            ['data' => $this->proveedor($proveedor)],
            Response::HTTP_CREATED,
        );
    }

    public function update(
        GuardarProveedorMaterialRequest $request,
        ProveedorMaterial $proveedorMaterial,
    ): JsonResponse {
        return response()->json([
            'data' => $this->proveedor($this->guardar($request, $proveedorMaterial)),
        ]);
    }

    private function guardar(
        GuardarProveedorMaterialRequest $request,
        ?ProveedorMaterial $proveedor = null,
    ): ProveedorMaterial {
        $datos = $request->validated();

        return DB::transaction(function () use ($datos, $request, $proveedor): ProveedorMaterial {
            $proveedor ??= new ProveedorMaterial;
            $proveedor->fill([
                'codigo' => $datos['codigo'],
                'nombre' => $datos['nombre'],
                'codigo_externo' => $datos['codigo_externo'],
                'activo' => $datos['activo'],
                'creado_por_user_id' => $proveedor->creado_por_user_id ?? $request->user()->id,
                'actualizado_por_user_id' => $request->user()->id,
            ]);
            $proveedor->save();

            ClienteProveedorMaterial::query()
                ->where('proveedor_material_id', $proveedor->id)
                ->update([
                    'activo' => false,
                    'actualizado_por_user_id' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            foreach ($datos['cliente_ids'] as $clienteId) {
                ClienteProveedorMaterial::query()->updateOrCreate(
                    [
                        'cliente_id' => $clienteId,
                        'proveedor_material_id' => $proveedor->id,
                    ],
                    [
                        'activo' => true,
                        'creado_por_user_id' => $request->user()->id,
                        'actualizado_por_user_id' => $request->user()->id,
                    ],
                );
            }

            return $proveedor->refresh()->load([
                'clientes' => fn ($consulta) => $consulta
                    ->wherePivot('activo', true)
                    ->orderBy('clientes.codigo'),
            ]);
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function proveedor(ProveedorMaterial $proveedor): array
    {
        return [
            'id' => $proveedor->id,
            'codigo' => $proveedor->codigo,
            'nombre' => $proveedor->nombre,
            'codigo_externo' => $proveedor->codigo_externo,
            'activo' => $proveedor->activo,
            'clientes' => $proveedor->clientes->map(fn ($cliente): array => [
                'id' => $cliente->id,
                'codigo' => $cliente->codigo,
                'nombre' => $cliente->nombre,
            ])->values(),
            'created_at' => $proveedor->created_at?->toAtomString(),
            'updated_at' => $proveedor->updated_at?->toAtomString(),
        ];
    }
}
