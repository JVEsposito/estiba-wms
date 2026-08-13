<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrigenAuditoriaIntegridadOperacional;
use App\Enums\SeveridadHallazgoIntegridadOperacional;
use App\Http\Controllers\Controller;
use App\Models\AuditoriaIntegridadOperacional;
use App\Models\HallazgoIntegridadOperacional;
use App\Services\IntegridadOperacional\ServicioAuditoriaIntegridadOperacional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IntegridadOperacionalController extends Controller
{
    public function __construct(
        private readonly ServicioAuditoriaIntegridadOperacional $servicio,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('consultar-integridad-operacional');

        $datos = validator($request->query(), [
            'estado' => ['nullable', Rule::in(['activos', 'resueltos', 'todos'])],
            'severidad' => ['nullable', Rule::enum(SeveridadHallazgoIntegridadOperacional::class)],
            'modulo' => ['nullable', 'string', 'max:60'],
            'regla' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:150'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:10', 'max:100'],
        ])->validate();

        $consulta = HallazgoIntegridadOperacional::query();
        match ($datos['estado'] ?? 'activos') {
            'resueltos' => $consulta->where('activo', false),
            'todos' => null,
            default => $consulta->where('activo', true),
        };

        if (filled($datos['severidad'] ?? null)) {
            $consulta->where('severidad', $datos['severidad']);
        }
        if (filled($datos['modulo'] ?? null)) {
            $consulta->where('modulo', $datos['modulo']);
        }
        if (filled($datos['regla'] ?? null)) {
            $consulta->where('regla_codigo', $datos['regla']);
        }
        if (filled($datos['q'] ?? null)) {
            $termino = trim((string) $datos['q']);
            $consulta->where(function ($filtro) use ($termino): void {
                $filtro->where('referencia', 'like', "%{$termino}%")
                    ->orWhere('titulo', 'like', "%{$termino}%")
                    ->orWhere('detalle', 'like', "%{$termino}%");
            });
        }

        $hallazgos = $consulta
            ->orderByRaw("CASE severidad WHEN 'critico' THEN 1 WHEN 'advertencia' THEN 2 ELSE 3 END")
            ->orderByDesc('detectado_ultima_vez_at')
            ->paginate(
                perPage: (int) ($datos['por_pagina'] ?? 25),
                page: (int) ($datos['pagina'] ?? 1),
            );

        $conteos = HallazgoIntegridadOperacional::query()
            ->where('activo', true)
            ->selectRaw('severidad, COUNT(*) as total')
            ->groupBy('severidad')
            ->pluck('total', 'severidad');
        $porModulo = HallazgoIntegridadOperacional::query()
            ->where('activo', true)
            ->selectRaw('modulo, COUNT(*) as total')
            ->groupBy('modulo')
            ->orderBy('modulo')
            ->pluck('total', 'modulo')
            ->map(fn (mixed $total): int => (int) $total);
        $auditorias = AuditoriaIntegridadOperacional::query()
            ->with('iniciadaPor:id,name')
            ->orderByDesc('iniciada_at')
            ->limit(8)
            ->get();

        return response()->json([
            'resumen' => [
                'activos' => (int) $conteos->sum(),
                'criticos' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Critico->value] ?? 0),
                'advertencias' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Advertencia->value] ?? 0),
                'informativos' => (int) ($conteos[SeveridadHallazgoIntegridadOperacional::Informativo->value] ?? 0),
                'resueltos_total' => HallazgoIntegridadOperacional::query()->where('activo', false)->count(),
                'por_modulo' => $porModulo,
            ],
            'ultima_auditoria' => $auditorias->first()
                ? $this->auditoria($auditorias->first())
                : null,
            'auditorias_recientes' => $auditorias
                ->map(fn (AuditoriaIntegridadOperacional $auditoria): array => $this->auditoria($auditoria))
                ->values(),
            'catalogo' => [
                'reglas' => $this->servicio->catalogoReglas(),
                'modulos' => $porModulo->keys()->values(),
            ],
            'data' => $hallazgos->getCollection()
                ->map(fn (HallazgoIntegridadOperacional $hallazgo): array => $this->hallazgo($hallazgo))
                ->values(),
            'meta' => [
                'pagina_actual' => $hallazgos->currentPage(),
                'ultima_pagina' => $hallazgos->lastPage(),
                'por_pagina' => $hallazgos->perPage(),
                'total' => $hallazgos->total(),
            ],
        ]);
    }

    public function auditar(Request $request): JsonResponse
    {
        Gate::authorize('ejecutar-integridad-operacional');

        $auditoria = $this->servicio->ejecutar(
            OrigenAuditoriaIntegridadOperacional::Manual,
            $request->user(),
        );

        return response()->json([
            'message' => $auditoria->hallazgos_activos === 0
                ? 'Auditoría completada sin contradicciones activas.'
                : "Auditoría completada con {$auditoria->hallazgos_activos} hallazgos activos.",
            'data' => $this->auditoria($auditoria->loadMissing('iniciadaPor:id,name')),
        ]);
    }

    /** @return array<string, mixed> */
    private function hallazgo(HallazgoIntegridadOperacional $hallazgo): array
    {
        return [
            'id' => $hallazgo->id,
            'regla_codigo' => $hallazgo->regla_codigo,
            'severidad' => $hallazgo->severidad->value,
            'modulo' => $hallazgo->modulo,
            'entidad_tipo' => $hallazgo->entidad_tipo,
            'entidad_id' => $hallazgo->entidad_id,
            'referencia' => $hallazgo->referencia,
            'titulo' => $hallazgo->titulo,
            'detalle' => $hallazgo->detalle,
            'contexto' => $hallazgo->contexto,
            'activo' => $hallazgo->activo,
            'ocurrencias' => $hallazgo->ocurrencias,
            'detectado_primera_vez_at' => $hallazgo->detectado_primera_vez_at?->toAtomString(),
            'detectado_ultima_vez_at' => $hallazgo->detectado_ultima_vez_at?->toAtomString(),
            'resuelto_at' => $hallazgo->resuelto_at?->toAtomString(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditoria(AuditoriaIntegridadOperacional $auditoria): array
    {
        return [
            'id' => $auditoria->id,
            'origen' => $auditoria->origen->value,
            'estado' => $auditoria->estado->value,
            'iniciada_por' => $auditoria->iniciadaPor ? [
                'id' => $auditoria->iniciadaPor->id,
                'nombre' => $auditoria->iniciadaPor->name,
            ] : null,
            'iniciada_at' => $auditoria->iniciada_at?->toAtomString(),
            'finalizada_at' => $auditoria->finalizada_at?->toAtomString(),
            'duracion_ms' => $auditoria->duracion_ms,
            'hallazgos_activos' => $auditoria->hallazgos_activos,
            'hallazgos_criticos' => $auditoria->hallazgos_criticos,
            'hallazgos_advertencia' => $auditoria->hallazgos_advertencia,
            'hallazgos_informativos' => $auditoria->hallazgos_informativos,
            'hallazgos_nuevos' => $auditoria->hallazgos_nuevos,
            'hallazgos_resueltos' => $auditoria->hallazgos_resueltos,
            'reglas_ejecutadas' => $auditoria->reglas_ejecutadas,
            'error' => $auditoria->error,
        ];
    }
}
