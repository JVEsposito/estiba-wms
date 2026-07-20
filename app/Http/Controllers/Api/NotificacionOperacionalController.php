<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacionOperacionalResource;
use App\Models\NotificacionOperacional;
use App\Services\Notificaciones\ServicioNotificacionesOperacionales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class NotificacionOperacionalController extends Controller
{
    public function index(
        Request $request,
        ServicioNotificacionesOperacionales $servicio,
    ): AnonymousResourceCollection {
        $filtros = $request->validate([
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
        ]);
        $usuario = $request->user();
        $notificaciones = $servicio
            ->consultaVisibles($usuario)
            ->with([
                'carga:id,codigo,prioridad,estado',
                'despachoMaterial:id,codigo,estado,destino_nombre,destino_centro_costo',
                'folio:id,numero_folio',
                'lecturas' => fn ($lectura) => $lectura
                    ->where('user_id', $usuario->id),
            ])
            ->orderByDesc('created_at')
            ->paginate((int) ($filtros['per_page'] ?? 50))
            ->withQueryString();

        return NotificacionOperacionalResource::collection($notificaciones)
            ->additional([
                'resumen' => [
                    'no_leidas' => $servicio->cantidadNoLeidas($usuario),
                    'sincronizado_at' => now()->toAtomString(),
                ],
            ]);
    }

    public function marcarLeida(
        Request $request,
        NotificacionOperacional $notificacionOperacional,
        ServicioNotificacionesOperacionales $servicio,
    ): NotificacionOperacionalResource {
        $servicio->marcarLeida($notificacionOperacional, $request->user());

        return new NotificacionOperacionalResource(
            $this->cargar($notificacionOperacional, $request),
        );
    }

    public function confirmar(
        Request $request,
        NotificacionOperacional $notificacionOperacional,
        ServicioNotificacionesOperacionales $servicio,
    ): NotificacionOperacionalResource {
        $servicio->confirmar($notificacionOperacional, $request->user());

        return new NotificacionOperacionalResource(
            $this->cargar($notificacionOperacional, $request),
        );
    }

    private function cargar(
        NotificacionOperacional $notificacion,
        Request $request,
    ): NotificacionOperacional {
        return $notificacion->load([
            'carga:id,codigo,prioridad,estado',
            'despachoMaterial:id,codigo,estado,destino_nombre,destino_centro_costo',
            'folio:id,numero_folio',
            'lecturas' => fn ($lectura) => $lectura
                ->where('user_id', $request->user()->id),
        ]);
    }
}
