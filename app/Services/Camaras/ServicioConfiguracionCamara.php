<?php

namespace App\Services\Camaras;

use App\Enums\EstadoPosicion;
use App\Models\Camara;
use App\Models\Posicion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioConfiguracionCamara
{
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
        return DB::transaction(function () use ($datos, $usuario): Camara {
            Camara::query()->orderBy('codigo')->lockForUpdate()->get(['id']);

            $camara = Camara::create([
                'codigo' => $this->siguienteCodigo(),
                'nombre' => trim($datos['nombre']),
                'tipo' => $datos['tipo'],
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

    public function etiqueta(int $banda, int $posicion, int $nivel): string
    {
        return sprintf('B%02d-P%02d-N%d', $banda, $posicion, $nivel);
    }

    private function clave(int $banda, int $posicion, int $nivel): string
    {
        return "{$banda}:{$posicion}:{$nivel}";
    }
}
