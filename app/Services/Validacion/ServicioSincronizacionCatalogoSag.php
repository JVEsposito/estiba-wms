<?php

namespace App\Services\Validacion;

use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\ProductorCsg;
use App\Models\Temporada;
use App\Models\VariedadValidacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioSincronizacionCatalogoSag
{
    public function __construct(
        private readonly ServicioProyeccionCatalogoValidacion $proyector,
    ) {}

    /**
     * @param  array<int, array{especie: string, variedad: string, texto: string}>  $pares
     * @return array{temporada_id: ?string, especies_creadas: int, variedades_creadas: int, variedades_vinculadas: int}
     */
    public function sincronizar(ProductorCsg $productor, array $pares): array
    {
        $resultado = [
            'temporada_id' => null,
            'especies_creadas' => 0,
            'variedades_creadas' => 0,
            'variedades_vinculadas' => 0,
        ];

        if ($pares === []) {
            return $resultado;
        }

        return DB::transaction(function () use ($productor, $pares, $resultado): array {
            $temporada = Temporada::query()
                ->where('activa', true)
                ->lockForUpdate()
                ->first();
            if (! $temporada) {
                return $resultado;
            }

            $resultado['temporada_id'] = $temporada->id;
            $variedadIds = [];
            $catalogoCambio = false;

            foreach ($pares as $par) {
                $nombreEspecie = $this->nombreCatalogo($par['especie']);
                $nombreVariedad = $this->nombreCatalogo($par['variedad']);

                $especie = EspecieValidacion::query()
                    ->where('temporada_id', $temporada->id)
                    ->where('nombre', $nombreEspecie)
                    ->first();
                if (! $especie) {
                    $especie = EspecieValidacion::query()->create([
                        'temporada_id' => $temporada->id,
                        'nombre' => $nombreEspecie,
                        'activo' => true,
                    ]);
                    $resultado['especies_creadas']++;
                    $catalogoCambio = true;
                }

                $variedad = VariedadValidacion::query()
                    ->where('especie_validacion_id', $especie->id)
                    ->where('nombre', $nombreVariedad)
                    ->first();
                if (! $variedad) {
                    $variedad = VariedadValidacion::query()->create([
                        'especie_validacion_id' => $especie->id,
                        'nombre' => $nombreVariedad,
                        'activo' => true,
                    ]);
                    $resultado['variedades_creadas']++;
                    $catalogoCambio = true;
                }

                $variedadIds[] = $variedad->id;
            }

            $csg = CsgValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper($productor->codigo)])
                ->first();
            if ($csg) {
                if (! $csg->productor_csg_id) {
                    $csg->update(['productor_csg_id' => $productor->id]);
                }

                $cambios = $csg->variedades()->syncWithoutDetaching(array_values(array_unique($variedadIds)));
                $resultado['variedades_vinculadas'] = count($cambios['attached']);
                $catalogoCambio = $catalogoCambio || $resultado['variedades_vinculadas'] > 0;
            }

            if ($catalogoCambio) {
                $temporada->increment('version_catalogo');
                $this->proyector->reconstruir($temporada->refresh());
            }

            return $resultado;
        }, attempts: 3);
    }

    private function nombreCatalogo(string $valor): string
    {
        return mb_convert_case(
            Str::of($valor)->squish()->toString(),
            MB_CASE_TITLE,
            'UTF-8',
        );
    }
}
