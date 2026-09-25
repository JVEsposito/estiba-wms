<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoriaPendienteCierre;
use App\Enums\MotivoRegularizacionCierre;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegularizarCierreTemporadaRequest;
use App\Models\RegularizacionCierreTemporada;
use App\Models\Temporada;
use App\Services\Temporadas\Cierre\ServicioAvisoCierreTemporada;
use App\Services\Temporadas\Cierre\ServicioDiagnosticoCierreTemporada;
use App\Services\Temporadas\Cierre\ServicioRegularizacionCierreTemporada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CierreTemporadaController extends Controller
{
    public function diagnostico(
        Temporada $temporada,
        ServicioDiagnosticoCierreTemporada $servicio,
    ): JsonResponse {
        return response()->json([
            'data' => $servicio->diagnosticar($temporada) + [
                'motivos' => array_map(fn (MotivoRegularizacionCierre $motivo): array => [
                    'valor' => $motivo->value,
                    'etiqueta' => $motivo->etiqueta(),
                ], MotivoRegularizacionCierre::cases()),
                'regularizaciones_recientes' => $this->recientes($temporada),
                'generado_at' => now()->toAtomString(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function regularizar(
        RegularizarCierreTemporadaRequest $request,
        Temporada $temporada,
        ServicioRegularizacionCierreTemporada $servicio,
    ): JsonResponse {
        $registros = $servicio->regularizar(
            $temporada,
            CategoriaPendienteCierre::from((string) $request->validated('categoria')),
            $request->validated('ids'),
            MotivoRegularizacionCierre::from((string) $request->validated('motivo_categoria')),
            (string) $request->validated('motivo'),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'regularizados' => $registros->count(),
                'lote_regularizacion_id' => $registros->first()?->lote_regularizacion_id,
            ],
        ]);
    }

    public function avisar(
        Request $request,
        Temporada $temporada,
        ServicioAvisoCierreTemporada $servicio,
    ): JsonResponse {
        return response()->json([
            'data' => $servicio->avisar($temporada, $request->user()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function recientes(Temporada $temporada): array
    {
        return RegularizacionCierreTemporada::query()
            ->with('regularizadoPor:id,name')
            ->where('temporada_id', $temporada->id)
            ->latest('regularizado_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (RegularizacionCierreTemporada $registro): array => [
                'id' => $registro->id,
                'categoria' => $registro->categoria->value,
                'etiqueta_categoria' => $registro->categoria->etiqueta(),
                'referencia' => $registro->referencia,
                'estado_anterior' => $registro->estado_anterior,
                'motivo_categoria' => $registro->motivo_categoria->value,
                'etiqueta_motivo' => $registro->motivo_categoria->etiqueta(),
                'motivo' => $registro->motivo,
                'regularizado_por' => $registro->regularizadoPor?->name,
                'regularizado_at' => $registro->regularizado_at?->toAtomString(),
            ])
            ->all();
    }
}
