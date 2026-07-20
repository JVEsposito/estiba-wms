<?php

namespace App\Services\Notificaciones;

use App\Enums\AudienciaNotificacionOperacional;
use App\Enums\ContenidoCamara;
use App\Enums\SeveridadNotificacionOperacional;
use App\Enums\TipoNotificacionOperacional;
use App\Models\DespachoMaterial;
use App\Models\LecturaNotificacionOperacional;
use App\Models\NotificacionOperacional;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServicioNotificacionesOperacionales
{
    public function __construct(private readonly AlcanceOperacionalUsuario $alcance) {}

    public function consultaVisibles(User $usuario): Builder
    {
        $areas = collect($this->alcance->contenidosVisibles($usuario))
            ->map(fn ($contenido): string => $contenido->value)
            ->all();

        return NotificacionOperacional::query()
            ->where(function (Builder $consulta) use ($usuario, $areas): void {
                $consulta
                    ->where(function (Builder $porUsuario) use ($usuario): void {
                        $porUsuario
                            ->where('audiencia_tipo', AudienciaNotificacionOperacional::Usuario->value)
                            ->where('audiencia_valor', (string) $usuario->id);
                    })
                    ->orWhere(function (Builder $porRol) use ($usuario): void {
                        $porRol
                            ->where('audiencia_tipo', AudienciaNotificacionOperacional::Rol->value)
                            ->where('audiencia_valor', $usuario->rol->value);
                    })
                    ->when($areas !== [], function (Builder $consultaConAreas) use ($areas): void {
                        $consultaConAreas->orWhere(function (Builder $porArea) use ($areas): void {
                            $porArea
                                ->where('audiencia_tipo', AudienciaNotificacionOperacional::Area->value)
                                ->whereIn('audiencia_valor', $areas);
                        });
                    });
            });
    }

    public function cantidadNoLeidas(User $usuario): int
    {
        return $this->consultaVisibles($usuario)
            ->whereDoesntHave(
                'lecturas',
                fn (Builder $lectura): Builder => $lectura
                    ->where('user_id', $usuario->id)
                    ->whereNotNull('leida_at'),
            )
            ->count();
    }

    public function notificarDespachoMaterialCreado(
        DespachoMaterial $despacho,
    ): NotificacionOperacional {
        $cantidadItems = $despacho->detalles()->count();

        return NotificacionOperacional::query()->firstOrCreate(
            ['clave' => "despacho-material:{$despacho->id}:area:materiales"],
            [
                'tipo' => TipoNotificacionOperacional::DespachoMaterialCreado,
                'audiencia_tipo' => AudienciaNotificacionOperacional::Area,
                'audiencia_valor' => ContenidoCamara::Materiales->value,
                'severidad' => SeveridadNotificacionOperacional::Informativa,
                'titulo' => "Nuevo despacho {$despacho->codigo}",
                'mensaje' => sprintf(
                    '%d %s para %s. Revisa las reservas FIFO y prepara el retiro.',
                    $cantidadItems,
                    $cantidadItems === 1 ? 'ítem solicitado' : 'ítems solicitados',
                    $despacho->destino_nombre,
                ),
                'despacho_material_id' => $despacho->id,
                'datos' => [
                    'destino' => $despacho->destino_nombre,
                    'centro_costo' => $despacho->destino_centro_costo,
                    'cantidad_items' => $cantidadItems,
                ],
            ],
        );
    }

    public function marcarLeida(
        NotificacionOperacional $notificacion,
        User $usuario,
    ): LecturaNotificacionOperacional {
        return $this->registrarEstado($notificacion, $usuario, false);
    }

    public function confirmar(
        NotificacionOperacional $notificacion,
        User $usuario,
    ): LecturaNotificacionOperacional {
        return $this->registrarEstado($notificacion, $usuario, true);
    }

    private function registrarEstado(
        NotificacionOperacional $notificacion,
        User $usuario,
        bool $confirmar,
    ): LecturaNotificacionOperacional {
        return DB::transaction(function () use ($notificacion, $usuario, $confirmar): LecturaNotificacionOperacional {
            $visible = $this->consultaVisibles($usuario)
                ->whereKey($notificacion->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lectura = LecturaNotificacionOperacional::query()
                ->where('notificacion_operacional_id', $visible->id)
                ->where('user_id', $usuario->id)
                ->lockForUpdate()
                ->first() ?? new LecturaNotificacionOperacional([
                    'notificacion_operacional_id' => $visible->id,
                    'user_id' => $usuario->id,
                ]);

            if ($lectura->leida_at === null) {
                $lectura->leida_at = now();
            }
            if ($confirmar) {
                if ($lectura->confirmada_at === null) {
                    $lectura->confirmada_at = now();
                }
            }
            $lectura->save();

            return $lectura->refresh();
        }, attempts: 3);
    }
}
