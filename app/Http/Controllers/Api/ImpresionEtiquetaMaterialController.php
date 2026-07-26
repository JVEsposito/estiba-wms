<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ConflictoOperacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarEtiquetasMaterialRequest;
use App\Http\Requests\RegistrarResultadoImpresionMaterialRequest;
use App\Models\PersonalAccessToken;
use App\Models\RecepcionMaterial;
use App\Models\TrabajoImpresionMaterial;
use App\Services\Materiales\ServicioImpresionEtiquetaMaterial;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    'enviado_at' => $trabajo->enviado_at?->toAtomString(),
                    'bytes_enviados' => $trabajo->bytes_enviados,
                    'destino_impresion' => $trabajo->destino_impresion_snapshot,
                    'ultimo_error' => $trabajo->ultimo_error,
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
        $datos = $request->validated();
        if ($datos['canal'] === 'pda_directa' && $dispositivoId === null) {
            throw new DomainException(
                'La impresión directa solo puede iniciarse desde una PDA o tablet registrada.',
            );
        }
        $resultado = $servicio->generar(
            $recepcionMaterial,
            $datos,
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

    public function resultado(
        RegistrarResultadoImpresionMaterialRequest $request,
        TrabajoImpresionMaterial $trabajoImpresionMaterial,
    ): JsonResponse {
        $token = $request->user()?->currentAccessToken();
        $dispositivoId = $token instanceof PersonalAccessToken
            ? $token->dispositivo_id
            : null;
        abort_unless(
            $trabajoImpresionMaterial->canal === 'pda_directa'
                && $trabajoImpresionMaterial->solicitado_por_user_id === $request->user()->id
                && $dispositivoId !== null
                && $trabajoImpresionMaterial->dispositivo_id === $dispositivoId,
            Response::HTTP_NOT_FOUND,
        );
        $datos = $request->validated();
        $payloadHash = hash('sha256', json_encode(
            [
                'estado' => $datos['estado'],
                'bytes_enviados' => $datos['bytes_enviados'],
                'error' => $datos['error'],
                'impresora' => $datos['impresora'],
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $trabajo = DB::transaction(function () use (
            $trabajoImpresionMaterial,
            $datos,
            $payloadHash,
        ): TrabajoImpresionMaterial {
            $trabajo = TrabajoImpresionMaterial::query()
                ->lockForUpdate()
                ->findOrFail($trabajoImpresionMaterial->id);

            if ($trabajo->resultado_operacion_id !== null) {
                if ($trabajo->resultado_operacion_id !== $datos['operacion_id']
                    || ! hash_equals((string) $trabajo->resultado_payload_hash, $payloadHash)) {
                    throw new ConflictoOperacion(
                        'El resultado de esta impresión ya fue informado y no puede reemplazarse.',
                    );
                }

                return $trabajo;
            }
            if ($trabajo->estado !== 'generado') {
                throw new ConflictoOperacion('El trabajo ya no admite un resultado de impresión.');
            }

            $trabajo->update([
                'resultado_operacion_id' => $datos['operacion_id'],
                'resultado_payload_hash' => $payloadHash,
                'destino_impresion_snapshot' => $datos['impresora'],
                'bytes_enviados' => $datos['bytes_enviados'],
                'estado' => $datos['estado'],
                'enviado_at' => $datos['estado'] === 'enviado' ? now() : null,
                'ultimo_error' => $datos['error'],
            ]);

            return $trabajo->refresh();
        }, attempts: 3);

        return response()->json([
            'data' => [
                'id' => $trabajo->id,
                'estado' => $trabajo->estado,
                'bytes_enviados' => $trabajo->bytes_enviados,
                'enviado_at' => $trabajo->enviado_at?->toAtomString(),
                'ultimo_error' => $trabajo->ultimo_error,
            ],
        ]);
    }
}
