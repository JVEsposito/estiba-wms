<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\TipoPlanOperacional;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanOperacionalResource;
use App\Http\Resources\TareaMovimientoResource;
use App\Models\PlanOperacional;
use App\Models\TareaMovimiento;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Estiba\ServicioReservasTareasMovimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlanOperacionalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::enum(TipoPlanOperacional::class)],
            'estado' => ['nullable', Rule::enum(EstadoPlanOperacional::class)],
            'prioridad' => ['nullable', Rule::enum(PrioridadOperacional::class)],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $planes = PlanOperacional::query()
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
            ->when(
                $filtros['tipo'] ?? null,
                fn (Builder $consulta, string $tipo): Builder => $consulta->where('tipo', $tipo),
            )
            ->when(
                $filtros['estado'] ?? null,
                fn (Builder $consulta, string $estado): Builder => $consulta->where('estado', $estado),
                fn (Builder $consulta): Builder => $consulta->whereNotIn('estado', [
                    EstadoPlanOperacional::Completado->value,
                    EstadoPlanOperacional::Cancelado->value,
                ]),
            )
            ->when(
                $filtros['prioridad'] ?? null,
                fn (Builder $consulta, string $prioridad): Builder => $consulta->where('prioridad', $prioridad),
            )
            ->with(['temporada:id,codigo,nombre', 'creadoPor:id,name', 'iniciadoPor:id,name'])
            ->withCount('tareas')
            ->orderByRaw($this->ordenPrioridad())
            ->orderBy('programado_at')
            ->paginate((int) ($filtros['per_page'] ?? 25))
            ->withQueryString();

        return PlanOperacionalResource::collection($planes);
    }

    public function show(
        PlanOperacional $planOperacional,
        ServicioReservasTareasMovimiento $reservas,
    ): PlanOperacionalResource {
        abort_unless($planOperacional->temporada()->where('activa', true)->exists(), 404);
        $reservas->expirarVencidas();

        return new PlanOperacionalResource($planOperacional->load([
            'temporada:id,codigo,nombre',
            'creadoPor:id,name',
            'iniciadoPor:id,name',
            'tareas.folio:id,numero_folio,tipo_bulto',
            'tareas.camaraOrigen:id,nombre',
            'tareas.posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
            'tareas.camaraDestino:id,nombre',
            'tareas.posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
            'tareas.responsable:id,name',
            'tareas.dispositivo:id,codigo,nombre',
            'tareas.reservaActiva:id,tarea_movimiento_id,bloqueo_tarea_id,bloqueo_posicion_id,estado,reservada_at,renovada_at,vence_at,version',
        ]));
    }

    public function tareas(
        Request $request,
        ServicioReservasTareasMovimiento $reservas,
    ): AnonymousResourceCollection {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoTareaMovimiento::class)],
            'prioridad' => ['nullable', Rule::enum(PrioridadOperacional::class)],
            'asignacion' => ['nullable', Rule::in(['disponibles', 'mias', 'todas'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $asignacion = $filtros['asignacion'] ?? 'disponibles';
        $reservas->expirarVencidas();

        $tareas = TareaMovimiento::query()
            ->whereHas('planOperacional.temporada', fn (Builder $consulta): Builder => $consulta->where('activa', true))
            ->whereHas('planOperacional', fn (Builder $consulta): Builder => $consulta->whereNotIn('estado', [
                EstadoPlanOperacional::Pausado->value,
                EstadoPlanOperacional::Completado->value,
                EstadoPlanOperacional::Cancelado->value,
            ]))
            ->when(
                $filtros['estado'] ?? null,
                fn (Builder $consulta, string $estado): Builder => $consulta->where('estado', $estado),
                fn (Builder $consulta): Builder => $consulta->whereIn('estado', [
                    EstadoTareaMovimiento::Pendiente->value,
                    EstadoTareaMovimiento::Asumida->value,
                    EstadoTareaMovimiento::EnProceso->value,
                ]),
            )
            ->when(
                $filtros['prioridad'] ?? null,
                fn (Builder $consulta, string $prioridad): Builder => $consulta->where('prioridad', $prioridad),
            )
            ->when(
                $asignacion === 'disponibles',
                fn (Builder $consulta): Builder => $consulta->whereNull('responsable_user_id'),
            )
            ->when(
                $asignacion === 'mias',
                fn (Builder $consulta): Builder => $consulta->where('responsable_user_id', $request->user()->id),
            )
            ->with($this->relacionesTarea())
            ->orderByRaw($this->ordenPrioridad())
            ->orderBy('created_at')
            ->orderBy('secuencia')
            ->paginate((int) ($filtros['per_page'] ?? 25))
            ->withQueryString();

        return TareaMovimientoResource::collection($tareas);
    }

    public function asumir(
        Request $request,
        TareaMovimiento $tareaMovimiento,
        ContextoOperacional $contexto,
        ServicioPlanesOperacionales $servicio,
    ): TareaMovimientoResource {
        [$usuario, $dispositivo] = $contexto->obtener($request);

        return new TareaMovimientoResource(
            $servicio->asumir($tareaMovimiento, $usuario, $dispositivo),
        );
    }

    public function liberar(
        Request $request,
        TareaMovimiento $tareaMovimiento,
        ContextoOperacional $contexto,
        ServicioPlanesOperacionales $servicio,
    ): TareaMovimientoResource {
        [$usuario, $dispositivo] = $contexto->obtener($request);

        return new TareaMovimientoResource(
            $servicio->liberar($tareaMovimiento, $usuario, $dispositivo),
        );
    }

    public function renovar(
        Request $request,
        TareaMovimiento $tareaMovimiento,
        ContextoOperacional $contexto,
        ServicioPlanesOperacionales $servicio,
    ): TareaMovimientoResource {
        [$usuario, $dispositivo] = $contexto->obtener($request);

        return new TareaMovimientoResource(
            $servicio->renovar($tareaMovimiento, $usuario, $dispositivo),
        );
    }

    /** @return array<int, string> */
    private function relacionesTarea(): array
    {
        return [
            'planOperacional:id,temporada_id,tipo,estado,prioridad,titulo,version',
            'folio:id,numero_folio,tipo_bulto',
            'camaraOrigen:id,nombre',
            'posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
            'camaraDestino:id,nombre',
            'posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
            'responsable:id,name',
            'dispositivo:id,codigo,nombre',
            'reservaActiva:id,tarea_movimiento_id,bloqueo_tarea_id,bloqueo_posicion_id,estado,reservada_at,renovada_at,vence_at,version',
        ];
    }

    private function ordenPrioridad(): string
    {
        return "CASE prioridad WHEN 'critica' THEN 1 WHEN 'urgente' THEN 2 WHEN 'alta' THEN 3 ELSE 4 END";
    }
}
