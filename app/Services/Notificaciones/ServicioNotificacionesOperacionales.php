<?php

namespace App\Services\Notificaciones;

use App\Enums\AudienciaNotificacionOperacional;
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
