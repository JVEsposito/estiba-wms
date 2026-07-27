<?php

namespace App\Services\Existencias;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
use Throwable;
use ZipArchive;

class GeneradorLibroXlsx
{
    /**
     * @param  array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>  $columnas
     * @param  iterable<int, array<string, mixed>>  $filas
     * @param  array<string, string|null>  $metadatos
     */
    public function generar(
        string $titulo,
        array $columnas,
        iterable $filas,
        array $metadatos,
    ): string {
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'estiba-xlsx-');
        if ($rutaTemporal === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de Excel.');
        }

        @unlink($rutaTemporal);
        $rutaTemporal .= '.xlsx';

        $zip = new ZipArchive;
        if ($zip->open($rutaTemporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible construir el archivo de Excel.');
        }

        $filasNormalizadas = is_array($filas) ? $filas : iterator_to_array($filas);
        $ultimaColumna = $this->nombreColumna(count($columnas));
        $filaEncabezados = 6;
        $ultimaFila = $filaEncabezados + count($filasNormalizadas);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($titulo));
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet(
            $titulo,
            $columnas,
            $filasNormalizadas,
            $metadatos,
            $ultimaColumna,
            $filaEncabezados,
            $ultimaFila,
        ));
        $zip->close();

        return $rutaTemporal;
    }

    private function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function rootRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function appProperties(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>Estiba WMS</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <Company>Estiba WMS</Company>
    <LinksUpToDate>false</LinksUpToDate>
    <SharedDoc>false</SharedDoc>
    <HyperlinksChanged>false</HyperlinksChanged>
    <AppVersion>1.0</AppVersion>
</Properties>
XML;
    }

    private function coreProperties(string $titulo): string
    {
        $creado = gmdate('Y-m-d\TH:i:s\Z');
        $tituloXml = $this->escapar($titulo);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>{$tituloXml}</dc:title>
    <dc:creator>Estiba WMS</dc:creator>
    <cp:lastModifiedBy>Estiba WMS</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$creado}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$creado}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>
    <sheets><sheet name="Existencia" sheetId="1" r:id="rId1"/></sheets>
    <calcPr calcId="191029"/>
</workbook>
XML;
    }

    private function workbookRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function styles(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="2">
        <numFmt numFmtId="164" formatCode="yyyy-mm-dd"/>
        <numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/>
    </numFmts>
    <fonts count="3">
        <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
        <font><b/><sz val="16"/><color rgb="FFF2F0E9"/><name val="Calibri"/><family val="2"/></font>
        <font><b/><sz val="11"/><color rgb="FF241D10"/><name val="Calibri"/><family val="2"/></font>
    </fonts>
    <fills count="4">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF141B22"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFC9AA68"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border><left style="thin"><color rgb="FF303A43"/></left><right style="thin"><color rgb="FF303A43"/></right><top style="thin"><color rgb="FF303A43"/></top><bottom style="thin"><color rgb="FF303A43"/></bottom><diagonal/></border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="7">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
        <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>
        <xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;
    }

    /**
     * @param  array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>  $columnas
     * @param  array<int, array<string, mixed>>  $filas
     * @param  array<string, string|null>  $metadatos
     */
    private function worksheet(
        string $titulo,
        array $columnas,
        array $filas,
        array $metadatos,
        string $ultimaColumna,
        int $filaEncabezados,
        int $ultimaFila,
    ): string {
        $columnasXml = '';
        foreach ($columnas as $indice => $columna) {
            $numero = $indice + 1;
            $ancho = max(10, min(60, (int) ($columna['ancho'] ?? 18)));
            $columnasXml .= "<col min=\"{$numero}\" max=\"{$numero}\" width=\"{$ancho}\" customWidth=\"1\"/>";
        }

        $filasXml = '';
        $filasXml .= $this->fila(1, [
            $this->celdaTexto('A1', $titulo, 1),
        ], 28);
        $filasXml .= $this->fila(2, [
            $this->celdaTexto('A2', 'Fecha de corte'),
            $this->celdaTexto('B2', $metadatos['fecha_corte'] ?? null),
        ]);
        $filasXml .= $this->fila(3, [
            $this->celdaTexto('A3', 'Generado por'),
            $this->celdaTexto('B3', $metadatos['usuario'] ?? null),
        ]);
        $filasXml .= $this->fila(4, [
            $this->celdaTexto('A4', 'Temporada'),
            $this->celdaTexto('B4', $metadatos['temporada'] ?? 'Temporada activa'),
        ]);

        $encabezados = [];
        foreach ($columnas as $indice => $columna) {
            $encabezados[] = $this->celdaTexto(
                $this->nombreColumna($indice + 1).$filaEncabezados,
                $columna['titulo'],
                2,
            );
        }
        $filasXml .= $this->fila($filaEncabezados, $encabezados, 32);

        foreach ($filas as $indiceFila => $fila) {
            $numeroFila = $filaEncabezados + $indiceFila + 1;
            $celdas = [];
            foreach ($columnas as $indiceColumna => $columna) {
                $referencia = $this->nombreColumna($indiceColumna + 1).$numeroFila;
                $valor = $fila[$columna['clave']] ?? null;
                $tipo = $columna['tipo'] ?? 'texto';
                if ($tipo === 'numero' && is_numeric($valor)) {
                    $celdas[] = $this->celdaNumero($referencia, (float) $valor, 4);
                } elseif (in_array($tipo, ['fecha', 'fecha_hora'], true) && $valor !== null && $valor !== '') {
                    $celdas[] = $this->celdaFecha($referencia, $valor, $tipo);
                } else {
                    $celdas[] = $this->celdaTexto($referencia, $valor, 3);
                }
            }
            $filasXml .= $this->fila($numeroFila, $celdas);
        }

        $filtroFinal = max($filaEncabezados, $ultimaFila);
        $tituloEscapado = $this->escapar($titulo);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetPr><tabColor rgb="FFC9AA68"/></sheetPr>
    <dimension ref="A1:{$ultimaColumna}{$filtroFinal}"/>
    <sheetViews>
        <sheetView workbookViewId="0">
            <pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>
            <selection pane="bottomLeft" activeCell="A7" sqref="A7"/>
        </sheetView>
    </sheetViews>
    <sheetFormatPr defaultRowHeight="15"/>
    <cols>{$columnasXml}</cols>
    <sheetData>{$filasXml}</sheetData>
    <autoFilter ref="A{$filaEncabezados}:{$ultimaColumna}{$filtroFinal}"/>
    <pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>
    <pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>
    <headerFooter><oddHeader>&amp;C{$tituloEscapado}</oddHeader><oddFooter>&amp;RPágina &amp;P de &amp;N</oddFooter></headerFooter>
</worksheet>
XML;
    }

    /** @param array<int, string> $celdas */
    private function fila(int $numero, array $celdas, ?int $alto = null): string
    {
        $atributos = $alto !== null ? " ht=\"{$alto}\" customHeight=\"1\"" : '';

        return '<row r="'.$numero.'"'.$atributos.'>'.implode('', $celdas).'</row>';
    }

    private function celdaTexto(string $referencia, mixed $valor, int $estilo = 0): string
    {
        $texto = $this->escapar($valor === null ? '' : (string) $valor);

        return "<c r=\"{$referencia}\" t=\"inlineStr\" s=\"{$estilo}\"><is><t xml:space=\"preserve\">{$texto}</t></is></c>";
    }

    private function celdaNumero(string $referencia, float $valor, int $estilo = 4): string
    {
        $numero = rtrim(rtrim(number_format($valor, 8, '.', ''), '0'), '.');
        if ($numero === '' || $numero === '-0') {
            $numero = '0';
        }

        return "<c r=\"{$referencia}\" s=\"{$estilo}\"><v>{$numero}</v></c>";
    }

    private function celdaFecha(string $referencia, mixed $valor, string $tipo): string
    {
        $serial = $this->serialFecha($valor, $tipo === 'fecha');
        if ($serial === null) {
            return $this->celdaTexto($referencia, $valor, 3);
        }

        return $this->celdaNumero($referencia, $serial, $tipo === 'fecha' ? 5 : 6);
    }

    private function serialFecha(mixed $valor, bool $soloFecha): ?float
    {
        try {
            $fecha = $valor instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($valor)
                : new DateTimeImmutable((string) $valor);
            $horaNeutral = $soloFecha
                ? $fecha->format('Y-m-d').' 00:00:00'
                : $fecha->format('Y-m-d H:i:s');
            $neutral = new DateTimeImmutable($horaNeutral, new DateTimeZone('UTC'));

            return ($neutral->getTimestamp() / 86400) + 25569;
        } catch (Throwable) {
            return null;
        }
    }

    private function nombreColumna(int $numero): string
    {
        $nombre = '';
        while ($numero > 0) {
            $numero--;
            $nombre = chr(65 + ($numero % 26)).$nombre;
            $numero = intdiv($numero, 26);
        }

        return $nombre;
    }

    private function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
