<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTemporadaGlobalRequest;
use App\Http\Requests\MigrarTemporadaRequest;
use App\Models\MigracionTemporada;
use App\Models\Temporada;
use App\Services\Temporadas\ServicioMigracionTemporada;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdministracionTemporadaController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('administrar-accesos');

        return response()->json([
            'data' => Temporada::query()
                ->with('configuracionMaterial:id,temporada_id')
                ->withCount('migracionesRecibidas')
                ->orderByDesc('activa')
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Temporada $temporada): array => $this->temporada($temporada)),
        ]);
    }

    public function store(
        GuardarTemporadaGlobalRequest $request,
        ServicioTemporadaGlobal $servicio,
    ): JsonResponse {
        $temporada = $servicio->guardar(
            $request->validated(),
            usuarioId: $request->user()->id,
        );

        return response()->json([
            'data' => $this->temporada($temporada->load('configuracionMaterial:id,temporada_id')),
        ], Response::HTTP_CREATED);
    }

    public function update(
        GuardarTemporadaGlobalRequest $request,
        Temporada $temporada,
        ServicioTemporadaGlobal $servicio,
    ): JsonResponse {
        $datos = $request->validated();
        $datos['activa'] = array_key_exists('activa', $datos)
            ? (bool) $datos['activa']
            : $temporada->activa;
        abort_if(
            $temporada->activa && $datos['activa'] === false,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Activa otra temporada para reemplazar la vigente.',
        );

        $temporada = $servicio->guardar(
            $datos,
            $temporada,
            $request->user()->id,
        );

        return response()->json([
            'data' => $this->temporada($temporada->load('configuracionMaterial:id,temporada_id')),
        ]);
    }

    public function activar(
        Request $request,
        Temporada $temporada,
        ServicioTemporadaGlobal $servicio,
    ): JsonResponse {
        Gate::authorize('administrar-accesos');
        $temporada = $servicio->activar($temporada, $request->user()->id);

        return response()->json([
            'data' => $this->temporada($temporada->load('configuracionMaterial:id,temporada_id')),
        ]);
    }

    public function migrar(
        MigrarTemporadaRequest $request,
        Temporada $temporada,
        ServicioMigracionTemporada $servicio,
    ): JsonResponse {
        $migracion = $servicio->migrar(
            Temporada::query()->findOrFail($request->validated('temporada_origen_id')),
            $temporada,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => $this->migracion($migracion),
        ], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function temporada(Temporada $temporada): array
    {
        return [
            'id' => $temporada->id,
            'configuracion_material_id' => $temporada->configuracionMaterial?->id,
            'codigo' => $temporada->codigo,
            'nombre' => $temporada->nombre,
            'fecha_inicio' => $temporada->fecha_inicio?->toDateString(),
            'fecha_fin' => $temporada->fecha_fin?->toDateString(),
            'activa' => $temporada->activa,
            'version_catalogo' => $temporada->version_catalogo,
            'migraciones_recibidas' => (int) ($temporada->migraciones_recibidas_count ?? 0),
            'created_at' => $temporada->created_at?->toAtomString(),
            'updated_at' => $temporada->updated_at?->toAtomString(),
        ];
    }

    /** @return array<string, mixed> */
    private function migracion(MigracionTemporada $migracion): array
    {
        return [
            'id' => $migracion->id,
            'origen' => [
                'id' => $migracion->origen->id,
                'codigo' => $migracion->origen->codigo,
                'nombre' => $migracion->origen->nombre,
            ],
            'destino' => [
                'id' => $migracion->destino->id,
                'codigo' => $migracion->destino->codigo,
                'nombre' => $migracion->destino->nombre,
                'activa' => $migracion->destino->activa,
            ],
            'resumen' => $migracion->resumen,
            'creado_por' => [
                'id' => $migracion->creadoPor->id,
                'nombre' => $migracion->creadoPor->name,
            ],
            'created_at' => $migracion->created_at?->toAtomString(),
        ];
    }
}
