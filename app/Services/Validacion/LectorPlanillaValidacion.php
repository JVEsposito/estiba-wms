<?php

namespace App\Services\Validacion;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class LectorPlanillaValidacion
{
    /**
     * @return array<int, array<string, string|int>>
     */
    public function leer(UploadedFile $archivo): array
    {
        $extension = mb_strtolower($archivo->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->leerCsv($archivo->getRealPath()),
            'xlsx' => $this->leerXlsx($archivo->getRealPath()),
            default => throw new DomainException('El archivo debe estar en formato CSV o XLSX.'),
        };
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function leerCsv(string $ruta): array
    {
        $manejador = fopen($ruta, 'rb');
        if ($manejador === false) {
            throw new DomainException('No fue posible abrir la planilla cargada.');
        }

        try {
            $primeraLinea = fgets($manejador);
            if ($primeraLinea === false) {
                return [];
            }

            $delimitador = $this->detectarDelimitador($primeraLinea);
            rewind($manejador);
            $filas = [];

            while (($fila = fgetcsv($manejador, 0, $delimitador)) !== false) {
                $filas[] = array_map(
                    fn ($valor): string => trim((string) $valor),
                    $fila,
                );
            }

            return $this->convertirFilas($filas);
        } finally {
            fclose($manejador);
        }
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function leerXlsx(string $ruta): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new DomainException('El servidor no posee la extensión ZIP necesaria para leer XLSX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) {
            throw new DomainException('No fue posible abrir el archivo XLSX.');
        }

        try {
            $compartidos = $this->leerTextosCompartidos($zip);
            $rutaHoja = $this->resolverPrimeraHoja($zip);
            $contenido = $zip->getFromName($rutaHoja);

            if ($contenido === false) {
                throw new DomainException('La planilla XLSX no contiene una hoja legible.');
            }

            $xml = simplexml_load_string($contenido);
            if (! $xml instanceof SimpleXMLElement) {
                throw new DomainException('La hoja del archivo XLSX no posee un XML válido.');
            }

            $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $filas = [];

            foreach ($xml->xpath('//m:sheetData/m:row') ?: [] as $filaXml) {
                $fila = [];
                foreach ($filaXml->c as $celda) {
                    $referencia = (string) $celda['r'];
                    $indice = $this->indiceColumna($referencia);
                    $tipo = (string) $celda['t'];
                    $valor = '';

                    if ($tipo === 'inlineStr') {
                        $valor = $this->textoNodo($celda->is);
                    } else {
                        $valorCrudo = (string) $celda->v;
                        $valor = $tipo === 's'
                            ? ($compartidos[(int) $valorCrudo] ?? '')
                            : $valorCrudo;
                    }

                    $fila[$indice] = trim($valor);
                }

                if ($fila !== []) {
                    ksort($fila);
                    $maximo = max(array_keys($fila));
                    $filas[] = array_map(
                        fn (int $indice): string => $fila[$indice] ?? '',
                        range(0, $maximo),
                    );
                }
            }

            return $this->convertirFilas($filas);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function leerTextosCompartidos(ZipArchive $zip): array
    {
        $contenido = $zip->getFromName('xl/sharedStrings.xml');
        if ($contenido === false) {
            return [];
        }

        $xml = simplexml_load_string($contenido);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $textos = [];
        foreach ($xml->si as $item) {
            $textos[] = $this->textoNodo($item);
        }

        return $textos;
    }

    private function resolverPrimeraHoja(ZipArchive $zip): string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($libro === false || $relaciones === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $xmlLibro = simplexml_load_string($libro);
        $xmlRelaciones = simplexml_load_string($relaciones);
        if (! $xmlLibro instanceof SimpleXMLElement || ! $xmlRelaciones instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $xmlLibro->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xmlLibro->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $hoja = ($xmlLibro->xpath('//m:sheets/m:sheet') ?: [])[0] ?? null;
        if (! $hoja instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $atributos = $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relacionId = (string) ($atributos['id'] ?? '');

        foreach ($xmlRelaciones->Relationship as $relacion) {
            if ((string) $relacion['Id'] !== $relacionId) {
                continue;
            }

            $destino = ltrim((string) $relacion['Target'], '/');

            return str_starts_with($destino, 'xl/') ? $destino : 'xl/'.$destino;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function textoNodo(SimpleXMLElement $nodo): string
    {
        $nodo->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $partes = $nodo->xpath('.//m:t') ?: [];

        return implode('', array_map(fn (SimpleXMLElement $texto): string => (string) $texto, $partes));
    }

    private function indiceColumna(string $referencia): int
    {
        preg_match('/^[A-Z]+/i', $referencia, $coincidencias);
        $letras = mb_strtoupper($coincidencias[0] ?? 'A');
        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = ($indice * 26) + (ord($letra) - 64);
        }

        return max(0, $indice - 1);
    }

    private function detectarDelimitador(string $linea): string
    {
        $conteos = [
            ';' => substr_count($linea, ';'),
            ',' => substr_count($linea, ','),
            "\t" => substr_count($linea, "\t"),
        ];
        arsort($conteos);

        return (string) array_key_first($conteos);
    }

    /**
     * @param  array<int, array<int, string>>  $filas
     * @return array<int, array<string, string|int>>
     */
    private function convertirFilas(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        $cabeceras = array_map($this->normalizarCabecera(...), array_shift($filas));
        $resultado = [];

        foreach ($filas as $indice => $fila) {
            $valores = [];
            foreach ($cabeceras as $columna => $cabecera) {
                if ($cabecera === '') {
                    continue;
                }

                $valores[$cabecera] = trim((string) ($fila[$columna] ?? ''));
            }

            if (collect($valores)->filter(fn (string $valor): bool => $valor !== '')->isEmpty()) {
                continue;
            }

            $resultado[] = ['fila' => $indice + 2, ...$valores];
        }

        return $resultado;
    }

    private function normalizarCabecera(string $cabecera): string
    {
        $normalizada = Str::of($cabecera)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($normalizada) {
            'especie', 'fruta' => 'especie',
            'variedad' => 'variedad',
            'calibre' => 'calibre',
            'envase', 'formato', 'packaging' => 'envase',
            'cliente', 'empresa', 'exportadora' => 'cliente',
            'marca' => 'marca',
            'csg', 'ggn', 'predio_codigo' => 'csg',
            'predio', 'nombre_predio' => 'predio',
            'codigo_articulo', 'articulo_codigo', 'sku' => 'codigo_articulo',
            'codigo_origen', 'origen_codigo' => 'codigo_origen',
            'codigo_combinacion', 'combinacion_codigo' => 'codigo_combinacion',
            default => $normalizada,
        };
    }
}
