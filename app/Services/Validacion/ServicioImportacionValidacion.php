<?php

namespace App\Services\Validacion;

use App\Models\ArticuloValidacion;
use App\Models\CombinacionValidacion;
use App\Models\ImportacionValidacion;
use App\Models\OrigenValidacion;
use App\Models\Temporada;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioImportacionValidacion
{
    public function __construct(
        private readonly LectorPlanillaValidacion $lector,
    ) {}

    public function previsualizar(
        UploadedFile $archivo,
        Temporada $temporada,
        User $usuario,
    ): ImportacionValidacion
    {
        $filasLeidas = $this->lector->leer($archivo);
        $errores = [];
        $filas = [];
        $vistas = [];

        foreach ($filasLeidas as $fila) {
            $normalizada = $this->normalizarFila($fila);
            $faltantes = collect([
                'especie',
                'variedad',
                'calibre',
                'envase',
                'cliente',
                'marca',
                'csg',
            ])->filter(fn (string $campo): bool => ($normalizada[$campo] ?? '') === '')->values();

            if ($faltantes->isNotEmpty()) {
                $errores[] = [
                    'fila' => $normalizada['fila'],
                    'mensaje' => 'Faltan campos obligatorios: '.$faltantes->implode(', ').'.',
                ];
                continue;
            }

            $clave = $this->claveFila($normalizada);
            if (isset($vistas[$clave])) {
                $errores[] = [
                    'fila' => $normalizada['fila'],
                    'mensaje' => sprintf(
                        'La combinación ya fue declarada en la fila %d.',
                        $vistas[$clave],
                    ),
                ];
                continue;
            }

            $vistas[$clave] = $normalizada['fila'];
            $filas[] = $normalizada;
        }

        $resumen = $this->calcularResumen($temporada, $filas, $errores);
        $checksum = hash_file('sha256', $archivo->getRealPath());

        return ImportacionValidacion::create([
            'temporada_id' => $temporada->id,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'tipo_archivo' => mb_strtolower($archivo->getClientOriginalExtension()),
            'checksum' => $checksum,
            'estado' => $errores === [] ? 'borrador' : 'con_errores',
            'resumen' => $resumen,
            'filas' => $filas,
            'errores' => $errores !== [] ? $errores : null,
            'creado_por_user_id' => $usuario->id,
        ])->load('temporada:id,codigo,nombre,version_catalogo');
    }

    public function confirmar(ImportacionValidacion $importacion, User $usuario): ImportacionValidacion
    {
        return DB::transaction(function () use ($importacion, $usuario): ImportacionValidacion {
            $importacion = ImportacionValidacion::query()
                ->lockForUpdate()
                ->findOrFail($importacion->id);

            if ($importacion->estado === 'confirmada') {
                return $importacion->load('temporada:id,codigo,nombre,version_catalogo');
            }

            if (($importacion->errores ?? []) !== []) {
                throw new DomainException('La importación posee errores y no puede confirmarse.');
            }

            $temporada = Temporada::query()->lockForUpdate()->findOrFail($importacion->temporada_id);
            $creados = ['articulos' => 0, 'origenes' => 0, 'combinaciones' => 0];
            $actualizados = ['articulos' => 0, 'origenes' => 0, 'combinaciones' => 0];

            foreach ($importacion->filas as $fila) {
                $articulo = ArticuloValidacion::query()->firstOrNew([
                    'temporada_id' => $temporada->id,
                    'especie' => $fila['especie'],
                    'variedad' => $fila['variedad'],
                    'calibre' => $fila['calibre'],
                    'envase' => $fila['envase'],
                ]);
                $articuloNuevo = !$articulo->exists;
                $articulo->fill([
                    'codigo_externo' => $fila['codigo_articulo'] ?: $articulo->codigo_externo,
                    'activo' => true,
                ]);
                $articuloCambio = $articuloNuevo || $articulo->isDirty();
                $articulo->save();

                if ($articuloCambio) {
                    $articuloNuevo ? $creados['articulos']++ : $actualizados['articulos']++;
                }

                $origen = OrigenValidacion::query()->firstOrNew([
                    'temporada_id' => $temporada->id,
                    'cliente' => $fila['cliente'],
                    'marca' => $fila['marca'],
                    'csg' => $fila['csg'],
                ]);
                $origenNuevo = !$origen->exists;
                $origen->fill([
                    'predio' => $fila['predio'] ?: $origen->predio,
                    'codigo_externo' => $fila['codigo_origen'] ?: $origen->codigo_externo,
                    'activo' => true,
                ]);
                $origenCambio = $origenNuevo || $origen->isDirty();
                $origen->save();

                if ($origenCambio) {
                    $origenNuevo ? $creados['origenes']++ : $actualizados['origenes']++;
                }

                $combinacion = CombinacionValidacion::query()->firstOrNew([
                    'temporada_id' => $temporada->id,
                    'articulo_validacion_id' => $articulo->id,
                    'origen_validacion_id' => $origen->id,
                ]);
                $combinacionNueva = !$combinacion->exists;
                $combinacion->fill([
                    'codigo_externo' => $fila['codigo_combinacion'] ?: $combinacion->codigo_externo,
                    'activo' => true,
                ]);
                $combinacionCambio = $combinacionNueva || $combinacion->isDirty();
                $combinacion->save();

                if ($combinacionCambio) {
                    $combinacionNueva ? $creados['combinaciones']++ : $actualizados['combinaciones']++;
                }
            }

            $huboCambios = array_sum($creados) + array_sum($actualizados) > 0;
            if ($huboCambios) {
                $temporada->increment('version_catalogo');
                $temporada->refresh();
            }

            $importacion->update([
                'estado' => 'confirmada',
                'resumen' => [
                    ...$importacion->resumen,
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'version_catalogo_resultante' => $temporada->version_catalogo,
                ],
                'confirmado_por_user_id' => $usuario->id,
                'confirmado_at' => now(),
            ]);

            return $importacion->refresh()->load('temporada:id,codigo,nombre,version_catalogo');
        }, attempts: 3);
    }

    /**
     * @param  array<string, string|int>  $fila
     * @return array<string, string|int>
     */
    private function normalizarFila(array $fila): array
    {
        return [
            'fila' => (int) $fila['fila'],
            'especie' => $this->texto($fila['especie'] ?? ''),
            'variedad' => $this->texto($fila['variedad'] ?? ''),
            'calibre' => mb_strtoupper($this->texto($fila['calibre'] ?? '')),
            'envase' => $this->texto($fila['envase'] ?? ''),
            'cliente' => $this->texto($fila['cliente'] ?? ''),
            'marca' => $this->texto($fila['marca'] ?? ''),
            'csg' => mb_strtoupper($this->texto($fila['csg'] ?? '')),
            'predio' => $this->texto($fila['predio'] ?? ''),
            'codigo_articulo' => mb_strtoupper($this->texto($fila['codigo_articulo'] ?? '')),
            'codigo_origen' => mb_strtoupper($this->texto($fila['codigo_origen'] ?? '')),
            'codigo_combinacion' => mb_strtoupper($this->texto($fila['codigo_combinacion'] ?? '')),
        ];
    }

    private function texto(mixed $valor): string
    {
        return Str::of((string) $valor)->squish()->toString();
    }

    /**
     * @param  array<string, string|int>  $fila
     */
    private function claveFila(array $fila): string
    {
        return mb_strtolower(implode('|', [
            $fila['especie'],
            $fila['variedad'],
            $fila['calibre'],
            $fila['envase'],
            $fila['cliente'],
            $fila['marca'],
            $fila['csg'],
        ]));
    }

    /**
     * @param  array<int, array<string, string|int>>  $filas
     * @param  array<int, array<string, string|int>>  $errores
     * @return array<string, int>
     */
    private function calcularResumen(Temporada $temporada, array $filas, array $errores): array
    {
        $articulos = collect($filas)->unique(fn (array $fila): string => mb_strtolower(implode('|', [
            $fila['especie'],
            $fila['variedad'],
            $fila['calibre'],
            $fila['envase'],
        ])));
        $origenes = collect($filas)->unique(fn (array $fila): string => mb_strtolower(implode('|', [
            $fila['cliente'],
            $fila['marca'],
            $fila['csg'],
        ])));

        $articulosNuevos = $articulos->filter(function (array $fila) use ($temporada): bool {
            return !ArticuloValidacion::query()->where([
                'temporada_id' => $temporada->id,
                'especie' => $fila['especie'],
                'variedad' => $fila['variedad'],
                'calibre' => $fila['calibre'],
                'envase' => $fila['envase'],
            ])->exists();
        })->count();
        $origenesNuevos = $origenes->filter(function (array $fila) use ($temporada): bool {
            return !OrigenValidacion::query()->where([
                'temporada_id' => $temporada->id,
                'cliente' => $fila['cliente'],
                'marca' => $fila['marca'],
                'csg' => $fila['csg'],
            ])->exists();
        })->count();

        return [
            'filas_validas' => count($filas),
            'filas_con_error' => count($errores),
            'articulos_detectados' => $articulos->count(),
            'articulos_nuevos_estimados' => $articulosNuevos,
            'origenes_detectados' => $origenes->count(),
            'origenes_nuevos_estimados' => $origenesNuevos,
            'combinaciones_detectadas' => count($filas),
        ];
    }
}
