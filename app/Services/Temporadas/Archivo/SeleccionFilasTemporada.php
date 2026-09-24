<?php

namespace App\Services\Temporadas\Archivo;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Determina qué filas pertenecen al archivo de una temporada, guardando sus claves en
 * archivo_temporada_claves:
 *
 * - «propia»: filas con una llave foránea a la temporada y, por cierre, sus filas hijas
 *   (una carga de la temporada, sus folios asignados, las incidencias de esos folios…).
 * - «referencia»: filas de otras tablas que las anteriores referencian (usuarios, cámaras,
 *   clientes, catálogos). Se incluyen solo las usadas, para que el archivo sea restaurable
 *   por sí mismo sin arrastrar los datos de otras temporadas.
 */
class SeleccionFilasTemporada
{
    public const PROPIA = 'propia';

    public const REFERENCIA = 'referencia';

    /** @var array<string, bool> */
    private array $propias = [];

    public function __construct(
        private readonly GrafoTablas $grafo,
        private readonly string $archivoId,
        private readonly string $temporadaId,
    ) {}

    public function ejecutar(): void
    {
        $this->determinarTablasPropias();
        $this->materializarPropias();
        $this->materializarReferencias();
    }

    /** @return array<int, string> Tablas con al menos una fila en el archivo. */
    public function tablasConFilas(): array
    {
        return collect($this->grafo->nombres())
            ->filter(fn (string $tabla): bool => $this->consultaFilas($tabla)->exists())
            ->values()
            ->all();
    }

    /** Consulta de las filas archivadas de una tabla (por sus claves o, sin clave simple, por relación). */
    public function consultaFilas(string $tabla): Builder
    {
        $pk = $this->grafo->clavePrimaria($tabla);
        if ($pk !== null) {
            return DB::table($tabla)->whereIn($pk, $this->claves($tabla));
        }

        // Sin clave simple (tablas pivote): solo sus filas ligadas a la temporada.
        return isset($this->propias[$tabla])
            ? DB::table($tabla)->where(fn (Builder $consulta) => $this->predicadoPropio($consulta, $tabla))
            : DB::table($tabla)->whereRaw('1 = 0');
    }

    /** @return array{propias: int, referencias: int} */
    public function conteo(string $tabla): array
    {
        if ($this->grafo->clavePrimaria($tabla) === null) {
            return ['propias' => $this->consultaFilas($tabla)->count(), 'referencias' => 0];
        }

        $conteos = DB::table('archivo_temporada_claves')
            ->where('archivo_id', $this->archivoId)
            ->where('tabla', $tabla)
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return [
            'propias' => (int) ($conteos[self::PROPIA] ?? 0),
            'referencias' => (int) ($conteos[self::REFERENCIA] ?? 0),
        ];
    }

    /** Una tabla es propia si referencia a temporadas o a otra tabla propia por su clave. */
    private function determinarTablasPropias(): void
    {
        do {
            $cambio = false;
            foreach ($this->grafo->nombres() as $tabla) {
                if ($tabla === 'temporadas' || isset($this->propias[$tabla])) {
                    continue;
                }

                foreach ($this->grafo->llavesForaneas($tabla) as $fk) {
                    if ($fk['tabla'] === $tabla) {
                        continue;
                    }
                    if ($fk['tabla'] === 'temporadas' || (isset($this->propias[$fk['tabla']])
                        && $fk['referencia'] === $this->grafo->clavePrimaria($fk['tabla']))) {
                        $this->propias[$tabla] = true;
                        $cambio = true;
                        break;
                    }
                }
            }
        } while ($cambio);
    }

    /** Inserta las claves propias hasta que ninguna tabla agregue filas nuevas (cubre ciclos). */
    private function materializarPropias(): void
    {
        $pendientes = array_keys($this->propias);

        while ($pendientes !== []) {
            $conNuevas = [];
            foreach ($pendientes as $tabla) {
                $pk = $this->grafo->clavePrimaria($tabla);
                if ($pk === null) {
                    continue;
                }

                $seleccion = DB::table($tabla)
                    ->selectRaw('?, ?, CAST('.$this->columna($tabla, $pk).' AS CHAR), ?', [$this->archivoId, $tabla, self::PROPIA])
                    ->where(fn (Builder $consulta) => $this->predicadoPropio($consulta, $tabla));

                if ($this->insertarClaves($seleccion) > 0) {
                    $conNuevas[] = $tabla;
                }
            }

            // Solo se vuelven a evaluar las tablas hijas de las que recibieron filas nuevas.
            $pendientes = collect(array_keys($this->propias))
                ->filter(fn (string $tabla): bool => collect($this->grafo->llavesForaneas($tabla))
                    ->contains(fn (array $fk): bool => in_array($fk['tabla'], $conNuevas, true)))
                ->values()
                ->all();
        }
    }

    /** Agrega las filas referenciadas por las ya incluidas, hasta un punto fijo. */
    private function materializarReferencias(): void
    {
        $pendientes = $this->grafo->nombres();

        while ($pendientes !== []) {
            $conNuevas = [];
            foreach ($pendientes as $tabla) {
                foreach ($this->grafo->llavesForaneas($tabla) as $fk) {
                    $destino = $fk['tabla'];
                    $pkDestino = $this->grafo->clavePrimaria($destino);
                    if (! $this->grafo->existe($destino) || $pkDestino === null) {
                        continue;
                    }

                    $valores = $this->consultaFilas($tabla)
                        ->whereNotNull($fk['columna'])
                        ->select($fk['columna']);
                    $seleccion = DB::table($destino)
                        ->selectRaw('?, ?, CAST('.$this->columna($destino, $pkDestino).' AS CHAR), ?', [$this->archivoId, $destino, self::REFERENCIA])
                        ->whereIn($fk['referencia'], $valores);

                    if ($this->insertarClaves($seleccion) > 0) {
                        $conNuevas[] = $destino;
                    }
                }
            }

            $pendientes = array_values(array_unique($conNuevas));
        }
    }

    private function predicadoPropio(Builder $consulta, string $tabla): void
    {
        foreach ($this->grafo->llavesForaneas($tabla) as $fk) {
            if ($fk['tabla'] === 'temporadas') {
                $consulta->orWhere($fk['columna'], $this->temporadaId);
            } elseif ($fk['tabla'] !== $tabla && isset($this->propias[$fk['tabla']])
                && $fk['referencia'] === $this->grafo->clavePrimaria($fk['tabla'])) {
                $consulta->orWhereIn($fk['columna'], $this->claves($fk['tabla'], self::PROPIA));
            }
        }
    }

    private function claves(string $tabla, ?string $tipo = null): Builder
    {
        return DB::table('archivo_temporada_claves')
            ->select('clave')
            ->where('archivo_id', $this->archivoId)
            ->where('tabla', $tabla)
            ->when($tipo, fn (Builder $consulta) => $consulta->where('tipo', $tipo));
    }

    private function insertarClaves(Builder $seleccion): int
    {
        return DB::affectingStatement(
            'INSERT IGNORE INTO archivo_temporada_claves (archivo_id, tabla, clave, tipo) '.$seleccion->toSql(),
            $seleccion->getBindings(),
        );
    }

    private function columna(string $tabla, string $columna): string
    {
        return DB::getQueryGrammar()->wrap($tabla.'.'.$columna);
    }
}
