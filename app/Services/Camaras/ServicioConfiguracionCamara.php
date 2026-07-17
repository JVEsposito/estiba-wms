<?php

namespace App\Services\Camaras;

use App\Enums\EstadoCamara;
use App\Enums\EstadoPosicion;
use App\Enums\ContenidoCamara;
use App\Exceptions\OperacionNoAutorizada;
use App\Models\Camara;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioConfiguracionCamara
{
    public function __construct(
        private readonly AlcanceOperacionalUsuario $alcance,
    ) {}

    public function siguienteCodigo(): string
    {
        $mayor = Camara::query()
            ->pluck('codigo')
            ->map(function (string $codigo): int {
                return preg_match('/^CAM-(\d+)$/', $codigo, $coincidencias)
                    ? (int) $coincidencias[1]
                    : 0;
            })
            ->max() ?? 0;

        return sprintf('CAM-%02d', $mayor + 1);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $usuario): Camara
    {
        $contenido = ContenidoCamara::from($datos['contenido']);

        if (! $this->alcance->puedeCrearCamara($usuario, $contenido)) {
            throw new OperacionNoAutorizada(
                'El usuario no puede crear cámaras para esta área.',
            );
        }

        return DB::transaction(function () use ($datos, $usuario): Camara {
            Camara::query()->orderBy('codigo')->lockForUpdate()->get(['id']);

            $camara = Camara::create([
                'codigo' => $this->siguienteCodigo(),
                'nombre' => trim($datos['nombre']),
                'tipo' => $datos['tipo'],
                'contenido' => $datos['contenido'],
                'estado' => EstadoCamara::Activa->value,
                'cantidad_bandas' => (int) $datos['bandas'],
                'posiciones_por_banda' => (int) $datos['posiciones_por_banda'],
                'cantidad_niveles' => (int) $datos['niveles'],
                'creado_por_user_id' => $usuario->id,
                'actualizado_por_user_id' => $usuario->id,
            ]);

            $fueraServicio = collect($datos['posiciones_fuera_servicio'] ?? [])
                ->mapWithKeys(fn (array $coordenada): array => [
                    $this->clave(
                        (int) $coordenada['banda'],
                        (int) $coordenada['posicion'],
                        (int) $coordenada['nivel'],
                    ) => true,
                ]);
            $ahora = now();
            $posiciones = [];

            for ($banda = 1; $banda <= (int) $datos['bandas']; $banda++) {
                for ($posicion = 1; $posicion <= (int) $datos['posiciones_por_banda']; $posicion++) {
                    for ($nivel = 1; $nivel <= (int) $datos['niveles']; $nivel++) {
                        $posiciones[] = [
                            'id' => (string) Str::uuid(),
                            'camara_id' => $camara->id,
                            'banda' => $banda,
                            'posicion' => $posicion,
                            'nivel' => $nivel,
                            'etiqueta' => $this->etiqueta($banda, $posicion, $nivel),
                            'estado' => $fueraServicio->has($this->clave($banda, $posicion, $nivel))
                                ? EstadoPosicion::FueraDeServicio->value
                                : EstadoPosicion::Activa->value,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ];
                    }
                }
            }

            foreach (array_chunk($posiciones, 250) as $lote) {
                Posicion::query()->insert($lote);
            }

            return $camara->loadCount([
                'posiciones',
                'posiciones as posiciones_activas_count' => fn ($consulta) => $consulta
                    ->where('estado', EstadoPosicion::Activa->value),
            ])->loadMax('posiciones', 'banda')
                ->loadMax('posiciones', 'posicion')
                ->loadMax('posiciones', 'nivel');
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Camara $camara, array $datos, User $usuario): Camara
    {
        if (! $this->alcance->puedeAdministrarCamaras($usuario)) {
            throw new OperacionNoAutorizada('Solo el administrador puede modificar cámaras.');
        }

        return DB::transaction(function () use ($camara, $datos, $usuario): Camara {
            $camaraBloqueada = Camara::query()->lockForUpdate()->findOrFail($camara->id);
            $this->asegurarSinSesionActiva($camaraBloqueada);

            $posiciones = Posicion::query()
                ->where('camara_id', $camaraBloqueada->id)
                ->with('ubicacionActual.folio:id,numero_folio')
                ->lockForUpdate()
                ->get();

            $estado = EstadoCamara::from(
                $datos['estado'] ?? $camaraBloqueada->estado->value,
            );

            if ($estado === EstadoCamara::Inactiva) {
                $this->asegurarSinFolios($posiciones);
            }

            if ($camaraBloqueada->contenido->value !== $datos['contenido']) {
                $this->asegurarSinFolios($posiciones);
            }

            $this->sincronizarPosiciones($camaraBloqueada, $posiciones, $datos);

            $camaraBloqueada->update([
                'nombre' => trim($datos['nombre']),
                'tipo' => $datos['tipo'],
                'contenido' => $datos['contenido'],
                'estado' => $estado,
                'cantidad_bandas' => (int) $datos['bandas'],
                'posiciones_por_banda' => (int) $datos['posiciones_por_banda'],
                'cantidad_niveles' => (int) $datos['niveles'],
                'version_plano' => $camaraBloqueada->version_plano + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);

            return $camaraBloqueada->refresh();
        }, attempts: 3);
    }

    public function desactivar(Camara $camara, User $usuario): Camara
    {
        if (! $this->alcance->puedeAdministrarCamaras($usuario)) {
            throw new OperacionNoAutorizada('Solo el administrador puede desactivar cámaras.');
        }

        return DB::transaction(function () use ($camara, $usuario): Camara {
            $camaraBloqueada = Camara::query()->lockForUpdate()->findOrFail($camara->id);
            $this->asegurarSinSesionActiva($camaraBloqueada);

            $posiciones = Posicion::query()
                ->where('camara_id', $camaraBloqueada->id)
                ->with('ubicacionActual.folio:id,numero_folio')
                ->lockForUpdate()
                ->get();

            $this->asegurarSinFolios($posiciones);

            $camaraBloqueada->update([
                'estado' => EstadoCamara::Inactiva,
                'version_plano' => $camaraBloqueada->version_plano + 1,
                'actualizado_por_user_id' => $usuario->id,
            ]);

            return $camaraBloqueada->refresh();
        }, attempts: 3);
    }

    public function etiqueta(int $banda, int $posicion, int $nivel): string
    {
        return sprintf('B%02d-P%02d-N%d', $banda, $posicion, $nivel);
    }

    private function clave(int $banda, int $posicion, int $nivel): string
    {
        return "{$banda}:{$posicion}:{$nivel}";
    }

    private function asegurarSinSesionActiva(Camara $camara): void
    {
        if ($camara->bloqueo()->exists()) {
            throw new DomainException(
                'La cámara está siendo modificada desde una tablet. Cierre esa sesión antes de administrarla.',
            );
        }
    }

    /**
     * @param  Collection<int, Posicion>  $posiciones
     */
    private function asegurarSinFolios(Collection $posiciones): void
    {
        $ocupada = $posiciones->first(fn (Posicion $posicion): bool => $posicion->ubicacionActual !== null);

        if ($ocupada) {
            throw new DomainException(sprintf(
                'La cámara contiene el folio %s en %s. Muévalo antes de desactivar la cámara.',
                $ocupada->ubicacionActual->folio->numero_folio,
                $ocupada->etiqueta,
            ));
        }
    }

    /**
     * @param  Collection<int, Posicion>  $posiciones
     * @param  array<string, mixed>  $datos
     */
    private function sincronizarPosiciones(
        Camara $camara,
        Collection $posiciones,
        array $datos,
    ): void {
        $bandas = (int) $datos['bandas'];
        $cantidadPosiciones = (int) $datos['posiciones_por_banda'];
        $niveles = (int) $datos['niveles'];
        $fueraServicio = collect($datos['posiciones_fuera_servicio'] ?? [])
            ->mapWithKeys(fn (array $coordenada): array => [
                $this->clave(
                    (int) $coordenada['banda'],
                    (int) $coordenada['posicion'],
                    (int) $coordenada['nivel'],
                ) => true,
            ]);
        $existentes = $posiciones->keyBy(
            fn (Posicion $posicion): string => $this->clave(
                $posicion->banda,
                $posicion->posicion,
                $posicion->nivel,
            ),
        );

        foreach ($posiciones as $posicion) {
            $dentroDelPlano = $posicion->banda <= $bandas
                && $posicion->posicion <= $cantidadPosiciones
                && $posicion->nivel <= $niveles;
            $fuera = $dentroDelPlano === false || $fueraServicio->has($this->clave(
                $posicion->banda,
                $posicion->posicion,
                $posicion->nivel,
            ));

            if ($fuera && $posicion->ubicacionActual) {
                throw new DomainException(sprintf(
                    'No se puede retirar %s: contiene el folio %s.',
                    $posicion->etiqueta,
                    $posicion->ubicacionActual->folio->numero_folio,
                ));
            }

            $posicion->update([
                'estado' => $fuera
                    ? EstadoPosicion::FueraDeServicio
                    : EstadoPosicion::Activa,
            ]);
        }

        for ($banda = 1; $banda <= $bandas; $banda++) {
            for ($posicion = 1; $posicion <= $cantidadPosiciones; $posicion++) {
                for ($nivel = 1; $nivel <= $niveles; $nivel++) {
                    $clave = $this->clave($banda, $posicion, $nivel);

                    if ($existentes->has($clave)) {
                        continue;
                    }

                    Posicion::create([
                        'camara_id' => $camara->id,
                        'banda' => $banda,
                        'posicion' => $posicion,
                        'nivel' => $nivel,
                        'etiqueta' => $this->etiqueta($banda, $posicion, $nivel),
                        'estado' => $fueraServicio->has($clave)
                            ? EstadoPosicion::FueraDeServicio
                            : EstadoPosicion::Activa,
                    ]);
                }
            }
        }
    }
}
