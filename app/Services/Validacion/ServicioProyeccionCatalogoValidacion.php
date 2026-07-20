<?php

namespace App\Services\Validacion;

use App\Models\ArticuloValidacion;
use App\Models\CombinacionValidacion;
use App\Models\OrigenValidacion;
use App\Models\Temporada;
use Illuminate\Support\Facades\DB;

class ServicioProyeccionCatalogoValidacion
{
    /**
     * @return array{articulos: int, origenes: int, combinaciones: int}
     */
    public function conteos(Temporada $temporada): array
    {
        return [
            'articulos' => ArticuloValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->count(),
            'origenes' => OrigenValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->count(),
            'combinaciones' => CombinacionValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->count(),
        ];
    }

    public function reconstruir(Temporada $temporada): void
    {
        DB::transaction(function () use ($temporada): void {
            $temporada = Temporada::query()
                ->lockForUpdate()
                ->findOrFail($temporada->id);

            $temporada->load([
                'clientes.marcas',
                'especies.variedades',
                'especies.calibres',
                'especies.envases',
                'csg.variedades:id',
            ]);

            ArticuloValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->whereNotNull('especie_validacion_id')
                ->update(['activo' => false]);
            OrigenValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->whereNotNull('csg_validacion_id')
                ->update(['activo' => false]);
            CombinacionValidacion::query()
                ->where('temporada_id', $temporada->id)
                ->whereHas('articulo', fn ($query) => $query->whereNotNull('especie_validacion_id'))
                ->whereHas('origen', fn ($query) => $query->whereNotNull('csg_validacion_id'))
                ->update(['activo' => false]);

            $articulos = [];
            foreach ($temporada->especies as $especie) {
                foreach ($especie->variedades as $variedad) {
                    foreach ($especie->calibres as $calibre) {
                        foreach ($especie->envases as $envase) {
                            $articulo = ArticuloValidacion::query()->firstOrNew([
                                'temporada_id' => $temporada->id,
                                'especie' => $especie->nombre,
                                'variedad' => $variedad->nombre,
                                'calibre' => $calibre->nombre,
                                'envase' => $envase->nombre,
                            ]);
                            $articulo->fill([
                                'especie_validacion_id' => $especie->id,
                                'variedad_validacion_id' => $variedad->id,
                                'calibre_validacion_id' => $calibre->id,
                                'envase_validacion_id' => $envase->id,
                                'activo' => $especie->activo
                                    && $variedad->activo
                                    && $calibre->activo
                                    && $envase->activo,
                            ])->save();

                            $articulos[] = [
                                'modelo' => $articulo,
                                'variedad_id' => $variedad->id,
                            ];
                        }
                    }
                }
            }

            $origenes = [];
            foreach ($temporada->clientes as $cliente) {
                foreach ($cliente->marcas as $marca) {
                    foreach ($temporada->csg as $csg) {
                        $origen = OrigenValidacion::query()->firstOrNew([
                            'temporada_id' => $temporada->id,
                            'cliente' => $cliente->nombre,
                            'marca' => $marca->nombre,
                            'csg' => $csg->codigo,
                        ]);
                        $origen->fill([
                            'cliente_validacion_id' => $cliente->id,
                            'marca_validacion_id' => $marca->id,
                            'csg_validacion_id' => $csg->id,
                            'predio' => $csg->predio,
                            'activo' => $cliente->activo && $marca->activo && $csg->activo,
                        ])->save();

                        $origenes[] = [
                            'modelo' => $origen,
                            'variedades' => $csg->variedades->pluck('id')->all(),
                        ];
                    }
                }
            }

            foreach ($articulos as $articulo) {
                foreach ($origenes as $origen) {
                    if (! in_array($articulo['variedad_id'], $origen['variedades'], true)) {
                        continue;
                    }

                    $combinacion = CombinacionValidacion::query()->firstOrNew([
                        'temporada_id' => $temporada->id,
                        'articulo_validacion_id' => $articulo['modelo']->id,
                        'origen_validacion_id' => $origen['modelo']->id,
                    ]);
                    $combinacion->fill([
                        'activo' => $articulo['modelo']->activo && $origen['modelo']->activo,
                    ])->save();
                }
            }
        }, attempts: 3);
    }
}
