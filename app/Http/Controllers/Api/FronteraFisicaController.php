<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ConflictoOperacion;
use App\Http\Controllers\Controller;
use App\Http\Resources\TareaMovimientoResource;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Planificador\ServicioFronteraFisica;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FronteraFisicaController extends Controller
{
    public function snapshot(
        Request $request,
        ContextoOperacional $contexto,
        ServicioFronteraFisica $frontera,
    ): JsonResponse {
        [$usuario, $dispositivo] = $contexto->obtener($request);

        return response()->json([
            'data' => $frontera->snapshot($usuario, $dispositivo),
        ]);
    }

    public function materializar(
        Request $request,
        ContextoOperacional $contexto,
        ServicioFronteraFisica $frontera,
        ServicioPlanesOperacionales $planes,
    ): JsonResponse {
        if (! config('planificador.generacion_automatica')
            || config('planificador.mode') !== 'guided'
            || config('planificador.compute') !== 'tablet'
            || config('planificador.horizon') !== 'rolling') {
            throw new DomainException(
                'La frontera física global requiere generación automática y el planificador guided/tablet/rolling.',
            );
        }

        $max = max(1, (int) config('planificador.frontier_max', 4));
        $datos = $request->validate([
            'snapshot_version' => ['required', 'string', 'size:64'],
            'planner_version' => ['required', 'string', 'max:80'],
            'propuestas' => ['required', 'array', 'min:1', "max:{$max}"],
            'propuestas.*.tarea_id' => ['required', 'uuid', 'distinct', 'exists:tareas_movimiento,id'],
            'propuestas.*.posicion_destino_id' => ['required', 'uuid', 'exists:posiciones,id'],
            'propuestas.*.tarea_version' => ['required', 'integer', 'min:1'],
            'propuestas.*.plan_version' => ['required', 'integer', 'min:1'],
            'propuestas.*.version_camara_conocida' => ['required', 'integer', 'min:0'],
            'propuestas.*.score' => ['nullable', 'numeric'],
            'propuestas.*.motivo' => ['nullable', 'string', 'max:240'],
        ]);
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $snapshot = $frontera->snapshot($usuario, $dispositivo);
        if (! hash_equals($snapshot['snapshot_version'], $datos['snapshot_version'])) {
            return response()->json([
                'message' => 'La realidad física cambió antes de materializar la frontera global.',
                'codigo' => 'snapshot_fisico_obsoleto',
                'data' => [
                    'snapshot_version' => $snapshot['snapshot_version'],
                    'aceptadas' => [],
                    'rechazadas' => $datos['propuestas'],
                ],
            ], 409);
        }

        $materializables = $frontera->idsMaterializables($snapshot);
        $aceptadas = [];
        $rechazadas = [];

        foreach ($datos['propuestas'] as $propuesta) {
            if (! $materializables->contains($propuesta['tarea_id'])) {
                $rechazadas[] = [
                    'tarea_id' => $propuesta['tarea_id'],
                    'posicion_destino_id' => $propuesta['posicion_destino_id'],
                    'motivo' => 'La tarea no es el paso actual materializable de esta tablet.',
                ];

                continue;
            }

            try {
                $tarea = TareaMovimiento::query()->findOrFail($propuesta['tarea_id']);
                $posicion = Posicion::query()->findOrFail($propuesta['posicion_destino_id']);
                $frontera->validarPropuestaDirigida($tarea, $posicion->camara_id);
                $materializada = $planes->materializarDestino(
                    tarea: $tarea,
                    posicion: $posicion,
                    usuario: $usuario,
                    dispositivo: $dispositivo,
                    versionTarea: (int) $propuesta['tarea_version'],
                    versionPlan: (int) $propuesta['plan_version'],
                    versionCamara: (int) $propuesta['version_camara_conocida'],
                );
                $aceptadas[] = [
                    'tarea' => (new TareaMovimientoResource($materializada))->resolve($request),
                    'score' => $propuesta['score'] ?? null,
                    'motivo' => $propuesta['motivo'] ?? null,
                    'planner_version' => $datos['planner_version'],
                ];
            } catch (ConflictoOperacion|DomainException $excepcion) {
                $rechazadas[] = [
                    'tarea_id' => $propuesta['tarea_id'],
                    'posicion_destino_id' => $propuesta['posicion_destino_id'],
                    'motivo' => $excepcion->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'aceptadas' => $aceptadas,
                'rechazadas' => $rechazadas,
                'recalcular' => $rechazadas !== [],
                'snapshot' => $frontera->snapshot($usuario, $dispositivo),
            ],
        ]);
    }
}
