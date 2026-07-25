<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarEtiquetasMaterialRequest;
use App\Models\PersonalAccessToken;
use App\Models\RecepcionMaterial;
use App\Models\TrabajoImpresionMaterial;
use App\Services\Materiales\ServicioImpresionEtiquetaMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ImpresionEtiquetaMaterialController extends Controller
{
    public function index(
        Request $request,
        RecepcionMaterial $recepcionMaterial,
    ): JsonResponse {
        Gate::authorize('consultar-recepciones-materiales');
        abort_unless(
            $recepcionMaterial->estado->value === 'confirmada'
                || $request->user()->can('anular-recepciones-materiales')
                || ($request->user()->can('gestionar-recepciones-materiales')
                    && $recepcionMaterial->creado_por_user_id === $request->user()->id),
            Response::HTTP_NOT_FOUND,
        );

        return response()->json([
            'data' => TrabajoImpresionMaterial::query()
                ->with(['folios', 'solicitadoPor:id,name'])
                ->where('recepcion_material_id', $recepcionMaterial->id)
                ->latest('solicitado_at')
                ->limit(100)
                ->get()
                ->map(fn (TrabajoImpresionMaterial $trabajo): array => [
                    'id' => $trabajo->id,
                    'operacion_id' => $trabajo->operacion_id,
                    'formato' => $trabajo->formato,
                    'canal' => $trabajo->canal,
                    'estado' => $trabajo->estado,
                    'copias' => $trabajo->copias,
                    'motivo_reimpresion' => $trabajo->motivo_reimpresion,
                    'perfil' => $trabajo->perfil_snapshot,
                    'folios' => $trabajo->folios->map(fn ($folio): array => [
                        'id' => $folio->folio_id,
                        'numero_folio' => $folio->numero_folio_snapshot,
                        'es_reimpresion' => $folio->es_reimpresion,
                    ])->values(),
                    'solicitado_por' => $trabajo->solicitadoPor?->name,
                    'solicitado_at' => $trabajo->solicitado_at?->toAtomString(),
                ]),
        ]);
    }

    public function store(
        GenerarEtiquetasMaterialRequest $request,
        RecepcionMaterial $recepcionMaterial,
        ServicioImpresionEtiquetaMaterial $servicio,
    ): Response {
        $token = $request->user()?->currentAccessToken();
        $dispositivoId = $token instanceof PersonalAccessToken
            ? $token->dispositivo_id
            : null;
        $resultado = $servicio->generar(
            $recepcionMaterial,
            $request->validated(),
            $request->user(),
            $dispositivoId,
        );

        return response($resultado['contenido'], Response::HTTP_OK, [
            'Content-Type' => $resultado['mime'],
            'Content-Disposition' => 'attachment; filename="'.$resultado['nombre'].'"',
            'X-Estiba-Print-Job' => $resultado['trabajo']->id,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
