<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarClienteMaterialRequest;
use App\Http\Requests\GuardarDestinoMaterialRequest;
use App\Http\Requests\GuardarItemMaterialRequest;
use App\Http\Requests\GuardarTemporadaMaterialRequest;
use App\Http\Resources\ClienteMaterialResource;
use App\Http\Resources\DestinoMaterialResource;
use App\Http\Resources\ItemMaterialResource;
use App\Http\Resources\TemporadaMaterialResource;
use App\Models\ClienteMaterial;
use App\Models\DestinoMaterial;
use App\Models\ItemMaterial;
use App\Models\Temporada;
use App\Models\TemporadaMaterial;
use App\Services\Clientes\ServicioCliente;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CatalogoMaterialController extends Controller
{
    public function catalogo(): JsonResponse
    {
        Gate::authorize('consultar-despachos-materiales');
        $temporada = TemporadaMaterial::query()->where('activa', true)->first();

        if (! $temporada) {
            return response()->json([
                'temporada' => null,
                'clientes' => [],
                'items' => [],
                'destinos' => DestinoMaterialResource::collection(
                    DestinoMaterial::query()->where('activo', true)->orderBy('nombre')->get(),
                ),
            ]);
        }

        return response()->json([
            'temporada' => new TemporadaMaterialResource($temporada),
            'clientes' => ClienteMaterialResource::collection(
                ClienteMaterial::query()
                    ->with('temporada')
                    ->where('temporada_material_id', $temporada->id)
                    ->where('activo', true)
                    ->withCount(['items as items_activos_count' => fn ($consulta) => $consulta->where('activo', true)])
                    ->orderBy('nombre')
                    ->get(),
            ),
            'items' => ItemMaterialResource::collection(
                ItemMaterial::query()
                    ->with('cliente.temporada')
                    ->where('activo', true)
                    ->whereHas('cliente', fn ($consulta) => $consulta
                        ->where('temporada_material_id', $temporada->id)
                        ->where('activo', true))
                    ->orderBy(ClienteMaterial::query()
                        ->select('nombre')
                        ->whereColumn('clientes_materiales.id', 'items_materiales.cliente_material_id'))
                    ->orderBy('nombre')
                    ->get(),
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
            ->with('cliente.temporada')
            ->withCount([
                'foliosMateriales as folios_activos_count' => fn ($consulta) => $consulta
                    ->whereHas('folio', fn ($folios) => $folios->where('activo', true)),
            ])
            ->orderByDesc(TemporadaMaterial::query()
                ->select('activa')
                ->join('clientes_materiales', 'clientes_materiales.temporada_material_id', '=', 'temporadas_materiales.id')
                ->whereColumn('clientes_materiales.id', 'items_materiales.cliente_material_id'))
            ->orderByDesc('activo')
            ->orderBy(ClienteMaterial::query()
                ->select('nombre')
                ->whereColumn('clientes_materiales.id', 'items_materiales.cliente_material_id'))
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => ItemMaterialResource::collection($items)]);
    }

    public function clientes(): JsonResponse
    {
        Gate::authorize('administrar-catalogos-materiales');

        $clientes = ClienteMaterial::query()
            ->with('temporada')
            ->withCount(['items as items_activos_count' => fn ($consulta) => $consulta->where('activo', true)])
            ->orderByDesc(TemporadaMaterial::query()
                ->select('activa')
                ->whereColumn('temporadas_materiales.id', 'clientes_materiales.temporada_material_id'))
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => ClienteMaterialResource::collection($clientes)]);
    }

    public function temporadas(): JsonResponse
    {
        Gate::authorize('administrar-catalogos-materiales');

        $temporadas = TemporadaMaterial::query()
            ->withCount([
                'clientes as clientes_activos_count' => fn ($consulta) => $consulta->where('activo', true),
                'items as items_activos_count' => fn ($consulta) => $consulta->where('items_materiales.activo', true),
            ])
            ->orderByDesc('activa')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => TemporadaMaterialResource::collection($temporadas)]);
    }

    public function storeTemporada(
        GuardarTemporadaMaterialRequest $request,
        ServicioTemporadaGlobal $temporadas,
    ): JsonResponse {
        $temporada = $this->guardarTemporada($request, $temporadas);

        return (new TemporadaMaterialResource($temporada))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateTemporada(
        GuardarTemporadaMaterialRequest $request,
        TemporadaMaterial $temporadaMaterial,
        ServicioTemporadaGlobal $temporadas,
    ): TemporadaMaterialResource {
        return new TemporadaMaterialResource($this->guardarTemporada($request, $temporadas, $temporadaMaterial));
    }

    public function activarTemporada(
        Request $request,
        TemporadaMaterial $temporadaMaterial,
        ServicioTemporadaGlobal $temporadas,
    ): TemporadaMaterialResource {
        Gate::authorize('administrar-catalogos-materiales');

        $temporada = DB::transaction(function () use ($request, $temporadaMaterial, $temporadas): TemporadaMaterial {
            $temporadas->activar($temporadaMaterial->temporadaGlobal()->firstOrFail());
            $temporadaMaterial->update(['actualizado_por_user_id' => $request->user()->id]);

            return $temporadaMaterial->refresh();
        });

        return new TemporadaMaterialResource($temporada);
    }

    public function storeCliente(
        GuardarClienteMaterialRequest $request,
        ServicioCliente $clientes,
    ): JsonResponse {
        $cliente = DB::transaction(function () use ($request, $clientes): ClienteMaterial {
            $cliente = ClienteMaterial::create([
                ...$request->validated(),
                'activo' => true,
                'creado_por_user_id' => $request->user()->id,
                'actualizado_por_user_id' => $request->user()->id,
            ]);
            $clientes->sincronizarMaterial($cliente, $request->user()->id);

            return $cliente->refresh();
        }, attempts: 3);

        return (new ClienteMaterialResource($cliente->load('temporada')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateCliente(
        GuardarClienteMaterialRequest $request,
        ClienteMaterial $clienteMaterial,
        ServicioCliente $clientes,
    ): ClienteMaterialResource {
        $datos = $request->validated();

        if ($clienteMaterial->temporada_material_id !== $datos['temporada_material_id']
            && $clienteMaterial->items()->exists()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La temporada no puede cambiar porque el cliente ya posee ítems.');
        }

        if (($datos['activo'] ?? true) === false
            && $clienteMaterial->items()->where('activo', true)->exists()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Desactiva primero los ítems activos del cliente.');
        }

        $clienteMaterial = DB::transaction(function () use (
            $clienteMaterial,
            $clientes,
            $datos,
            $request,
        ): ClienteMaterial {
            $clienteMaterial->update([
                ...$datos,
                'actualizado_por_user_id' => $request->user()->id,
            ]);
            $clientes->sincronizarMaterial($clienteMaterial, $request->user()->id);

            return $clienteMaterial->refresh();
        }, attempts: 3);

        return new ClienteMaterialResource($clienteMaterial->refresh()->load(['temporada', 'cliente']));
    }

    public function storeItem(GuardarItemMaterialRequest $request): JsonResponse
    {
        $this->validarClienteActivo($request->validated('cliente_material_id'));
        $item = ItemMaterial::create([
            ...$request->validated(),
            'activo' => true,
            'origen_sistema' => 'manual',
            'creado_por_user_id' => $request->user()->id,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return (new ItemMaterialResource($item->load('cliente.temporada')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateItem(
        GuardarItemMaterialRequest $request,
        ItemMaterial $itemMaterial,
    ): ItemMaterialResource {
        $datos = $request->validated();
        $this->validarClienteActivo($datos['cliente_material_id']);

        if ($itemMaterial->foliosMateriales()->exists()) {
            unset($datos['unidad_medida']);
            if ($itemMaterial->cliente_material_id !== $datos['cliente_material_id']
                && $itemMaterial->cliente()->value('codigo') !== 'GENERAL') {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'El cliente no puede cambiar porque el ítem ya posee folios asociados.');
            }
        }

        $itemMaterial->update([
            ...$datos,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return new ItemMaterialResource($itemMaterial->refresh()->load('cliente.temporada'));
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

    private function validarClienteActivo(string $clienteId): void
    {
        abort_unless(
            ClienteMaterial::query()->whereKey($clienteId)->where('activo', true)->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'El cliente de materiales no existe o se encuentra inactivo.',
        );
    }

    private function guardarTemporada(
        GuardarTemporadaMaterialRequest $request,
        ServicioTemporadaGlobal $temporadas,
        ?TemporadaMaterial $temporada = null,
    ): TemporadaMaterial {
        $datos = $request->validated();
        abort_if(
            $temporada?->activa === true && ($datos['activa'] ?? true) === false,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Activa otra temporada para reemplazar la vigente.',
        );

        return DB::transaction(function () use ($datos, $request, $temporada, $temporadas): TemporadaMaterial {
            $temporadaGlobal = $temporada?->temporadaGlobal
                ?? Temporada::query()->where('codigo', $datos['codigo'])->first();
            if ($temporadaGlobal && TemporadaMaterial::query()
                ->where('temporada_id', $temporadaGlobal->id)
                ->when($temporada, fn ($consulta) => $consulta->whereKeyNot($temporada->id))
                ->exists()) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La temporada global ya posee configuración de materiales.');
            }

            $activa = $datos['activa'] ?? null;
            if ($activa === null && $temporadaGlobal) {
                $activa = $temporadaGlobal->activa;
            }
            $temporadaGlobal = $temporadas->guardar([
                ...$datos,
                'activa' => (bool) ($activa ?? false),
            ], $temporadaGlobal);
            $temporada ??= new TemporadaMaterial;
            $temporada->fill([
                'temporada_id' => $temporadaGlobal->id,
                'codigo' => $temporadaGlobal->codigo,
                'nombre' => $temporadaGlobal->nombre,
                'fecha_inicio' => $temporadaGlobal->fecha_inicio,
                'fecha_fin' => $temporadaGlobal->fecha_fin,
                'activa' => $temporadaGlobal->activa,
                'creado_por_user_id' => $temporada->creado_por_user_id ?? $request->user()->id,
                'actualizado_por_user_id' => $request->user()->id,
            ]);
            $temporada->save();

            if ($temporada->activa) {
                TemporadaMaterial::query()->whereKeyNot($temporada->id)->update(['activa' => false]);
            }

            return $temporada->refresh();
        });
    }
}
