<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarArchivoTemporada;
use App\Models\ArchivoTemporada;
use App\Models\Temporada;
use App\Services\Temporadas\Archivo\ServicioArchivoTemporada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Archivo de temporadas cerradas: solo el administrador lo genera y lo descarga. */
class ArchivoTemporadaController extends Controller
{
    public function index(Temporada $temporada, ServicioArchivoTemporada $servicio): JsonResponse
    {
        $motivo = $servicio->motivoNoElegible($temporada);

        return response()->json([
            'data' => [
                'temporada' => ['id' => $temporada->id, 'codigo' => $temporada->codigo],
                'elegible' => $motivo === null,
                'motivo' => $motivo,
                'archivos' => ArchivoTemporada::query()
                    ->where('temporada_id', $temporada->id)
                    ->with('solicitadoPor:id,name')
                    ->latest()
                    ->get()
                    ->map(fn (ArchivoTemporada $archivo): array => $this->archivo($archivo))
                    ->all(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Temporada $temporada, ServicioArchivoTemporada $servicio): JsonResponse
    {
        $archivo = $servicio->solicitar($temporada, $request->user());
        ProcesarArchivoTemporada::dispatch($archivo->id);

        return response()->json(['data' => $this->archivo($archivo->refresh()->load('solicitadoPor:id,name'))], 202);
    }

    public function descargar(ArchivoTemporada $archivo): StreamedResponse
    {
        abort_unless($archivo->estado === ServicioArchivoTemporada::VERIFICADO && $archivo->ruta, 409, 'El archivo todavía no está verificado.');

        return Storage::disk($archivo->disco)->download($archivo->ruta, basename($archivo->ruta), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array<string, mixed> */
    private function archivo(ArchivoTemporada $archivo): array
    {
        $manifiesto = $archivo->manifiesto ?? [];

        return [
            'id' => $archivo->id,
            'estado' => $archivo->estado,
            'solicitado_por' => $archivo->solicitadoPor?->name,
            'creado_at' => $archivo->created_at?->toAtomString(),
            'verificado_at' => $archivo->verificado_at?->toAtomString(),
            'tamano_bytes' => $archivo->tamano_bytes,
            'sha256' => $archivo->sha256,
            'tablas' => count($manifiesto['tablas'] ?? []),
            'filas' => array_sum(array_column($manifiesto['tablas'] ?? [], 'filas')),
            'excel' => array_keys($manifiesto['excel'] ?? []),
            'mensaje_error' => $archivo->mensaje_error,
        ];
    }
}
