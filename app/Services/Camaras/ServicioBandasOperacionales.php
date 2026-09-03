<?php

namespace App\Services\Camaras;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoPosicion;
use App\Enums\ModoBandaOperacional;
use App\Enums\UsoBandaOperacional;
use App\Models\BandaOperacional;
use App\Models\Camara;
use App\Models\Posicion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicioBandasOperacionales
{
    public function sincronizar(Camara $camara, ?User $usuario = null): void
    {
        if ($camara->contenido !== ContenidoCamara::Productos) {
            return;
        }

        for ($numero = 1; $numero <= $camara->cantidad_bandas; $numero++) {
            BandaOperacional::query()->firstOrCreate(
                [
                    'camara_id' => $camara->id,
                    'numero' => $numero,
                ],
                [
                    'usos_permitidos' => UsoBandaOperacional::valores(),
                    'modo' => ModoBandaOperacional::Operativa,
                    'actualizado_por_user_id' => $usuario?->id,
                    'version' => 1,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function configurar(
        Camara $camara,
        BandaOperacional $banda,
        array $datos,
        User $usuario,
    ): BandaOperacional {
        return DB::transaction(function () use ($camara, $banda, $datos, $usuario): BandaOperacional {
            $camaraBloqueada = Camara::query()->lockForUpdate()->findOrFail($camara->id);
            $bandaBloqueada = BandaOperacional::query()->lockForUpdate()->findOrFail($banda->id);

            if ($camaraBloqueada->contenido !== ContenidoCamara::Productos) {
                throw new DomainException('Solo las cámaras de producto terminado poseen bandas operacionales.');
            }

            if ($bandaBloqueada->camara_id !== $camaraBloqueada->id
                || $bandaBloqueada->numero > $camaraBloqueada->cantidad_bandas) {
                throw new DomainException('La banda no pertenece al plano vigente de la cámara.');
            }

            if ($camaraBloqueada->bloqueo()->exists()) {
                throw new DomainException(
                    'La cámara está siendo modificada desde una tablet. Cierre esa sesión antes de configurar la banda.',
                );
            }

            if ($bandaBloqueada->version !== (int) $datos['version']) {
                throw new DomainException(
                    'La banda cambió desde la última consulta. Actualice el plano antes de volver a guardar.',
                );
            }

            $ordenUsos = array_flip(UsoBandaOperacional::valores());
            $usos = collect($datos['usos_permitidos'])
                ->unique()
                ->sortBy(fn (string $uso): int => (int) $ordenUsos[$uso])
                ->values()
                ->all();
            $modo = ModoBandaOperacional::from($datos['modo']);

            $bandaBloqueada->update([
                'usos_permitidos' => $usos,
                'modo' => $modo,
                'motivo_estado' => $modo === ModoBandaOperacional::Operativa
                    ? null
                    : trim((string) $datos['motivo_estado']),
                'actualizado_por_user_id' => $usuario->id,
                'version' => $bandaBloqueada->version + 1,
            ]);

            $camaraBloqueada->update([
                'version_plano' => $camaraBloqueada->version_plano + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);

            return $bandaBloqueada->refresh();
        }, attempts: 3);
    }

    public function enriquecer(Camara $camara): Camara
    {
        $camara->loadMissing([
            'bandasOperacionales' => fn ($consulta) => $consulta
                ->where('numero', '<=', $camara->cantidad_bandas)
                ->with('actualizadoPor:id,name'),
            'posiciones.ubicacionesActuales:id,posicion_id',
        ]);

        /** @var Collection<int, Posicion> $posiciones */
        $posiciones = $camara->posiciones
            ->filter(fn ($posicion): bool => $posicion->banda <= $camara->cantidad_bandas
                && $posicion->posicion <= $camara->posiciones_por_banda
                && $posicion->nivel <= $camara->cantidad_niveles);
        $porBanda = $posiciones->groupBy('banda');

        foreach ($camara->bandasOperacionales as $banda) {
            $coordenadas = $porBanda->get($banda->numero, collect());
            $activas = $coordenadas->filter(
                fn ($posicion): bool => $posicion->estado === EstadoPosicion::Activa,
            );
            $ocupadas = $activas->filter(
                fn ($posicion): bool => $posicion->ubicacionesActuales->isNotEmpty(),
            )->count();

            $banda->setAttribute('capacidad_fisica_calculada', $coordenadas->count());
            $banda->setAttribute('capacidad_efectiva_calculada', $activas->count());
            $banda->setAttribute('ocupadas_calculadas', $ocupadas);
        }

        return $camara;
    }
}
