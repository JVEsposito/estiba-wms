<?php

namespace App\Services\Temporadas\Archivo;

use App\Models\ArchivoTemporada;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Existencias\GeneradorLibroXlsx;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Archivo completo de una temporada cerrada: esquema, datos restaurables (INSERT), Excel de
 * líneas limpias y un manifiesto con conteos y huellas SHA-256. Generar un archivo nunca
 * borra datos; la depuración es un paso posterior que exige un archivo verificado.
 */
class ServicioArchivoTemporada
{
    public const EN_PROCESO = 'en_proceso';

    public const VERIFICADO = 'verificado';

    public const FALLIDO = 'fallido';

    private const FILAS_POR_INSERT = 500;

    private const MAX_TEXTO_EXCEL = 32000;

    public function __construct(private readonly GeneradorLibroXlsx $excel) {}

    /** Motivo por el que la temporada todavía no puede archivarse, o null si puede. */
    public function motivoNoElegible(Temporada $temporada): ?string
    {
        if ($temporada->activa) {
            return 'La temporada activa no se archiva.';
        }
        if ($temporada->fecha_fin === null) {
            return 'Registra la fecha de fin de la temporada antes de archivarla.';
        }

        $dias = (int) config('archivo_temporadas.dias_minimos_cierre', 60);
        $disponible = CarbonImmutable::parse($temporada->fecha_fin)->addDays($dias)->startOfDay();
        if ($disponible->isFuture()) {
            return "La temporada se conserva al menos {$dias} días después de su cierre: podrá archivarse desde el {$disponible->format('d-m-Y')}.";
        }

        return null;
    }

    public function solicitar(Temporada $temporada, User $usuario): ArchivoTemporada
    {
        if ($motivo = $this->motivoNoElegible($temporada)) {
            throw new DomainException($motivo);
        }
        if (ArchivoTemporada::query()->where('temporada_id', $temporada->id)->where('estado', self::EN_PROCESO)->exists()) {
            throw new DomainException('Ya hay un archivo en preparación para esta temporada.');
        }

        return ArchivoTemporada::create([
            'temporada_id' => $temporada->id,
            'estado' => self::EN_PROCESO,
            'disco' => config('archivo_temporadas.disco'),
            'solicitado_por_user_id' => $usuario->id,
        ]);
    }

    /** Genera y verifica el paquete. Ante cualquier error queda «fallido» con su mensaje. */
    public function procesar(ArchivoTemporada $archivo): ArchivoTemporada
    {
        $directorio = $this->directorioTemporal();
        $archivo->forceFill(['iniciado_at' => now(), 'mensaje_error' => null])->save();

        try {
            $this->generar($archivo, $directorio);
            $this->verificar($archivo->refresh());
        } catch (Throwable $error) {
            $archivo->forceFill([
                'estado' => self::FALLIDO,
                'mensaje_error' => mb_substr($error->getMessage(), 0, 2000),
            ])->save();
            report($error);
        } finally {
            DB::table('archivo_temporada_claves')->where('archivo_id', $archivo->id)->delete();
            $this->eliminarDirectorio($directorio);
        }

        return $archivo->refresh();
    }

    /** Relee el paquete desde su disco y compara cada archivo con el manifiesto. */
    public function verificar(ArchivoTemporada $archivo): void
    {
        $manifiesto = $archivo->manifiesto ?? [];
        $local = $this->descargarTemporal($archivo);

        try {
            if (hash_file('sha256', $local) !== $archivo->sha256) {
                throw new RuntimeException('La huella del paquete no coincide con la registrada.');
            }

            $zip = new ZipArchive;
            if ($zip->open($local) !== true) {
                throw new RuntimeException('No fue posible abrir el paquete para verificarlo.');
            }

            try {
                $this->comprobarEntrada($zip, 'esquema.sql', $manifiesto['esquema']['sha256'] ?? '');
                foreach ($manifiesto['tablas'] ?? [] as $tabla => $datos) {
                    $filas = $this->comprobarEntrada($zip, "datos/{$tabla}.sql", $datos['sha256']);
                    if ($filas !== $datos['filas']) {
                        throw new RuntimeException("La tabla {$tabla} tiene {$filas} filas en el paquete y {$datos['filas']} en el manifiesto.");
                    }
                }
                foreach ($manifiesto['excel'] ?? [] as $tabla => $datos) {
                    $this->comprobarEntrada($zip, "excel/{$tabla}.xlsx", $datos['sha256']);
                }
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($local);
        }

        $archivo->forceFill(['estado' => self::VERIFICADO, 'verificado_at' => now()])->save();
    }

    private function generar(ArchivoTemporada $archivo, string $directorio): void
    {
        $temporada = $archivo->temporada;
        $grafo = new GrafoTablas(config('archivo_temporadas.tablas_excluidas', []));
        $seleccion = new SeleccionFilasTemporada($grafo, $archivo->id, $temporada->id);
        $seleccion->ejecutar();
        $tablas = $seleccion->tablasConFilas();

        mkdir($directorio.'/datos', 0700, true);
        mkdir($directorio.'/excel', 0700, true);
        $manifiesto = [
            'version' => 1,
            'temporada' => [
                'id' => $temporada->id,
                'codigo' => $temporada->codigo,
                'nombre' => $temporada->nombre,
                'fecha_inicio' => $temporada->fecha_inicio?->toDateString(),
                'fecha_fin' => $temporada->fecha_fin?->toDateString(),
            ],
            'generado_at' => now()->toAtomString(),
            'generado_por' => $archivo->solicitadoPor?->name,
            'base' => [
                'motor' => DB::connection()->getDriverName(),
                'version' => DB::scalar('select version()'),
                'ultima_migracion' => DB::table('migrations')->orderByDesc('id')->value('migration'),
            ],
            'restauracion' => 'Crear una base vacía, ejecutar esquema.sql y luego cada archivo de datos/.',
            'tablas' => [],
            'excel' => [],
        ];

        $manifiesto['esquema'] = ['sha256' => $this->escribirEsquema($directorio.'/esquema.sql', $tablas)];
        foreach ($tablas as $tabla) {
            $ruta = "{$directorio}/datos/{$tabla}.sql";
            $filas = $this->escribirDatos($ruta, $tabla, $grafo, $seleccion);
            $manifiesto['tablas'][$tabla] = [
                ...$seleccion->conteo($tabla),
                'filas' => $filas,
                'sha256' => hash_file('sha256', $ruta),
            ];

            if (in_array($tabla, config('archivo_temporadas.tablas_excel', []), true)) {
                $manifiesto['excel'][$tabla] = $this->escribirExcel($directorio, $tabla, $grafo, $seleccion, $temporada, $filas);
            }
        }

        file_put_contents($directorio.'/manifiesto.json', json_encode($manifiesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $zipLocal = $this->empaquetar($directorio, $tablas, array_keys($manifiesto['excel']));

        try {
            // El código lo escribe un usuario: solo se usan caracteres seguros para la ruta.
            $codigo = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $temporada->codigo) ?? '', '-') ?: 'temporada';
            $ruta = sprintf('%s/%s_%s.zip', $codigo, $codigo, now()->format('Ymd_His'));
            $flujo = fopen($zipLocal, 'rb');
            Storage::disk($archivo->disco)->writeStream($ruta, $flujo);
            if (is_resource($flujo)) {
                fclose($flujo);
            }

            $archivo->forceFill([
                'ruta' => $ruta,
                'tamano_bytes' => filesize($zipLocal),
                'sha256' => hash_file('sha256', $zipLocal),
                'manifiesto' => $manifiesto,
                'generado_at' => now(),
            ])->save();
        } finally {
            @unlink($zipLocal);
        }
    }

    /** @param array<int, string> $tablas */
    private function escribirEsquema(string $ruta, array $tablas): string
    {
        $salida = fopen($ruta, 'wb');
        fwrite($salida, "-- Esquema de las tablas archivadas.\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tablas as $tabla) {
            $definicion = (array) DB::selectOne('SHOW CREATE TABLE '.DB::getQueryGrammar()->wrapTable($tabla));
            fwrite($salida, array_values($definicion)[1].";\n\n");
        }
        fwrite($salida, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($salida);

        return hash_file('sha256', $ruta);
    }

    /** Escribe INSERT por bloques, una fila por línea (así se cuentan al verificar). */
    private function escribirDatos(string $ruta, string $tabla, GrafoTablas $grafo, SeleccionFilasTemporada $seleccion): int
    {
        $salida = fopen($ruta, 'wb');
        $columnas = $grafo->columnas($tabla);
        $encabezado = 'INSERT INTO '.DB::getQueryGrammar()->wrapTable($tabla).' ('
            .implode(', ', array_map(fn (string $columna): string => DB::getQueryGrammar()->wrap($columna), $columnas))
            .") VALUES\n";
        fwrite($salida, "-- Tabla {$tabla}\nSET FOREIGN_KEY_CHECKS=0;\n");
        $total = 0;

        foreach ($this->recorrer($tabla, $grafo, $seleccion) as $bloque) {
            $lineas = array_map(fn (array $fila): string => '('.implode(', ', array_map(
                fn (mixed $valor): string => $this->literal($valor),
                array_map(fn (string $columna) => $fila[$columna] ?? null, $columnas),
            )).')', $bloque);
            fwrite($salida, $encabezado.implode(",\n", $lineas).";\n");
            $total += count($bloque);
        }

        fwrite($salida, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($salida);

        return $total;
    }

    /** @return array{filas: int, sha256: string} */
    private function escribirExcel(string $directorio, string $tabla, GrafoTablas $grafo, SeleccionFilasTemporada $seleccion, Temporada $temporada, int $filas): array
    {
        $columnas = array_map(fn (string $columna): array => ['clave' => $columna, 'titulo' => $columna], $grafo->columnas($tabla));
        $generador = function () use ($tabla, $grafo, $seleccion) {
            foreach ($this->recorrer($tabla, $grafo, $seleccion) as $bloque) {
                foreach ($bloque as $fila) {
                    yield array_map(
                        fn (mixed $valor) => is_string($valor) && mb_strlen($valor) > self::MAX_TEXTO_EXCEL
                            ? mb_substr($valor, 0, self::MAX_TEXTO_EXCEL).'…'
                            : $valor,
                        $fila,
                    );
                }
            }
        };
        $temporal = $this->excel->generar($tabla, $columnas, $generador(), [
            'fecha_corte' => now()->toAtomString(),
            'temporada' => $temporada->codigo,
        ], mb_substr($tabla, 0, 31));
        $destino = "{$directorio}/excel/{$tabla}.xlsx";
        rename($temporal, $destino);

        return ['filas' => $filas, 'sha256' => hash_file('sha256', $destino)];
    }

    /**
     * Recorre las filas archivadas por bloques ordenados por clave (sin OFFSET).
     *
     * @return \Generator<int, array<int, array<string, mixed>>>
     */
    private function recorrer(string $tabla, GrafoTablas $grafo, SeleccionFilasTemporada $seleccion): \Generator
    {
        $pk = $grafo->clavePrimaria($tabla);
        if ($pk === null) {
            foreach ($seleccion->consultaFilas($tabla)->get()->chunk(self::FILAS_POR_INSERT) as $bloque) {
                yield $bloque->map(fn ($fila): array => (array) $fila)->values()->all();
            }

            return;
        }

        $ultimo = null;
        do {
            $bloque = $seleccion->consultaFilas($tabla)
                ->when($ultimo !== null, fn ($consulta) => $consulta->where($pk, '>', $ultimo))
                ->orderBy($pk)
                ->limit(self::FILAS_POR_INSERT)
                ->get()
                ->map(fn ($fila): array => (array) $fila)
                ->all();
            if ($bloque === []) {
                return;
            }

            yield $bloque;
            $ultimo = end($bloque)[$pk];
        } while (count($bloque) === self::FILAS_POR_INSERT);
    }

    private function literal(mixed $valor): string
    {
        return match (true) {
            $valor === null => 'NULL',
            is_bool($valor) => $valor ? '1' : '0',
            is_int($valor), is_float($valor) => (string) $valor,
            default => DB::getPdo()->quote((string) $valor),
        };
    }

    /**
     * Compara la huella de una entrada del paquete con el manifiesto leyéndola por flujo, y
     * devuelve cuántas filas de datos contiene (una por línea que empieza con «(»).
     */
    private function comprobarEntrada(ZipArchive $zip, string $entrada, string $huella): int
    {
        $flujo = $zip->getStream($entrada);
        if ($flujo === false) {
            throw new RuntimeException("Falta {$entrada} en el paquete.");
        }

        $contexto = hash_init('sha256');
        $filas = 0;
        while (($linea = fgets($flujo)) !== false) {
            hash_update($contexto, $linea);
            if (str_starts_with($linea, '(')) {
                $filas++;
            }
        }
        fclose($flujo);

        if (! hash_equals($huella, hash_final($contexto))) {
            throw new RuntimeException("El archivo {$entrada} no coincide con el manifiesto.");
        }

        return $filas;
    }

    /**
     * @param  array<int, string>  $tablas
     * @param  array<int, string>  $excel
     */
    private function empaquetar(string $directorio, array $tablas, array $excel): string
    {
        $ruta = $directorio.'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el paquete del archivo.');
        }

        $zip->addFile($directorio.'/manifiesto.json', 'manifiesto.json');
        $zip->addFile($directorio.'/esquema.sql', 'esquema.sql');
        foreach ($tablas as $tabla) {
            $zip->addFile("{$directorio}/datos/{$tabla}.sql", "datos/{$tabla}.sql");
        }
        foreach ($excel as $tabla) {
            $zip->addFile("{$directorio}/excel/{$tabla}.xlsx", "excel/{$tabla}.xlsx");
            $zip->setCompressionName("excel/{$tabla}.xlsx", ZipArchive::CM_STORE);
        }
        if (! $zip->close()) {
            throw new RuntimeException('No fue posible cerrar el paquete del archivo.');
        }

        return $ruta;
    }

    private function descargarTemporal(ArchivoTemporada $archivo): string
    {
        $local = tempnam(sys_get_temp_dir(), 'estiba-archivo-');
        $origen = Storage::disk($archivo->disco)->readStream($archivo->ruta);
        $destino = fopen($local, 'wb');
        stream_copy_to_stream($origen, $destino);
        fclose($destino);
        if (is_resource($origen)) {
            fclose($origen);
        }

        return $local;
    }

    private function directorioTemporal(): string
    {
        $ruta = sys_get_temp_dir().'/estiba-archivo-'.bin2hex(random_bytes(8));
        mkdir($ruta, 0700, true);

        return $ruta;
    }

    private function eliminarDirectorio(string $ruta): void
    {
        if (! is_dir($ruta)) {
            @unlink($ruta.'.zip');

            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entrada) {
            $entrada->isDir() ? rmdir($entrada->getPathname()) : unlink($entrada->getPathname());
        }
        rmdir($ruta);
        @unlink($ruta.'.zip');
    }
}
