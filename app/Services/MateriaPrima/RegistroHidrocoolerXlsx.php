<?php

namespace App\Services\MateriaPrima;

use App\Models\ProcesoHidrocoolerMateriaPrima;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use ZipArchive;

class RegistroHidrocoolerXlsx
{
    private const FILAS_EN_BLANCO = 10;

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos */
    public function generar(Collection $procesos): string
    {
        return $this->generarLibro($procesos->values(), false);
    }

    public function generarEnBlanco(): string
    {
        return $this->generarLibro(collect(), true);
    }

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos */
    private function generarLibro(Collection $procesos, bool $enBlanco): string
    {
        $ruta = $this->rutaTemporal('estiba-hidrocooler-', '.xlsx');
        $zip = null;

        try {
            $zip = new ZipArchive;
            if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible construir el registro de Hidrocooler.');
            }

            $titulo = 'Registro de control de Hidrocooler'.($enBlanco ? ' en blanco' : '');
            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('docProps/app.xml', $this->appProperties());
            $zip->addFromString('docProps/core.xml', $this->coreProperties($titulo));
            $zip->addFromString('xl/workbook.xml', $this->workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($procesos, $enBlanco));

            if ($zip->close() === false) {
                throw new RuntimeException('No fue posible finalizar el registro de Hidrocooler.');
            }
            $zip = null;

            return $ruta;
        } catch (Throwable $excepcion) {
            if ($zip instanceof ZipArchive) {
                $zip->close();
            }
            if (is_file($ruta)) {
                unlink($ruta);
            }

            throw $excepcion;
        }
    }

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos */
    private function sheet(Collection $procesos, bool $enBlanco): string
    {
        $cantidadFilas = $enBlanco ? self::FILAS_EN_BLANCO : max(1, $procesos->count());
        $filaInicio = 8;
        $filaFin = $filaInicio + $cantidadFilas - 1;
        $filaObservacion = $filaFin + 2;
        $filaFirmas = $filaObservacion + 3;
        $ultimaFila = $filaFirmas + 2;
        $contexto = $this->contexto($procesos);
        $filas = [];

        $filas[] = $this->row(1, [
            $this->inlineCell('A1', 'REGISTRO DE CONTROL DE HIDROCOOLER', 1),
            $this->inlineCell('K1', 'CÓDIGO', 2),
            $this->inlineCell('M1', 'POR DEFINIR', 3),
        ], 30);
        $filas[] = $this->row(2, [
            $this->inlineCell('K2', 'VERSIÓN', 2),
            $this->inlineCell('M2', '0', 3),
        ], 22);
        $filas[] = $this->row(3, [
            $this->inlineCell('A3', 'Control operacional y PCC · trazabilidad por lote/ciclo', 4),
            $this->inlineCell('K3', 'FECHA', 2),
            $this->inlineCell('M3', $contexto['fecha'], 3),
        ], 22);
        $filas[] = $this->row(4, [
            $this->inlineCell('A4', 'Fecha', 2),
            $this->inlineCell('C4', $contexto['fecha'], 3),
            $this->inlineCell('E4', 'Equipo', 2),
            $this->inlineCell('G4', $contexto['equipo'], 3),
            $this->inlineCell('I4', 'Turno', 2),
            $this->inlineCell('K4', $contexto['turno'], 3),
            $this->inlineCell('L4', 'Operador', 2),
            $this->inlineCell('N4', $contexto['operador'], 3),
        ], 22);
        $filas[] = $this->row(5, [
            $this->inlineCell('A5', 'Temporada', 2),
            $this->inlineCell('C5', $contexto['temporada'], 3),
            $this->inlineCell('E5', 'Frecuencia mínima', 2),
            $this->inlineCell('G5', 'Inicio · durante proceso · cambio de lote · recambio de agua', 3),
        ], 26);
        $filas[] = $this->row(6, [
            $this->inlineCell(
                'A6',
                'REFERENCIA DE CONTROL: cloro libre 80-120 ppm y pH 6-7, o el rango vigente validado por la planta. Ante una desviación: detener, ajustar, recircular o renovar agua, retener fruta desde el último control conforme y documentar la acción.',
                5,
            ),
        ], 34);

        $encabezados = [
            'N°', 'Fecha', "Equipo\nTurno", "Ciclo\nLote", "Recepción\nGuía", "CSG · Cuartel\nVariedad",
            "Envases\nKg netos", 'Bombas', "Inicio · Término\nDuración", "T° fruta\nInicial · Obj. · Final",
            "T° agua\nInicial · Final", "Cloro ppm\npH", "Agua visual · Dosif.\nFiltrado/recambio",
            'Destino', "Observaciones\nAcción correctiva",
        ];
        $celdasEncabezado = [];
        foreach ($encabezados as $indice => $encabezado) {
            $celdasEncabezado[] = $this->inlineCell($this->columna($indice + 1).'7', $encabezado, 6);
        }
        $filas[] = $this->row(7, $celdasEncabezado, 42);

        for ($indice = 0; $indice < $cantidadFilas; $indice++) {
            $fila = $filaInicio + $indice;
            $proceso = $procesos->get($indice);
            $valores = $proceso ? $this->valores($proceso, $indice + 1) : array_fill(0, 15, '');
            if (! $proceso) {
                $valores[0] = (string) ($indice + 1);
            }
            $celdas = [];
            foreach ($valores as $columna => $valor) {
                $estilo = in_array($columna, [0, 1, 2, 7, 13], true) ? 8 : 7;
                $celdas[] = $this->inlineCell($this->columna($columna + 1).$fila, $valor, $estilo);
            }
            $filas[] = $this->row($fila, $celdas, 48);
        }

        $filas[] = $this->row($filaObservacion, [
            $this->inlineCell('A'.$filaObservacion, 'OBSERVACIONES GENERALES / DESVIACIONES / RETENCIÓN DE PRODUCTO', 2),
        ], 22);
        $filas[] = $this->row($filaObservacion + 1, [
            $this->inlineCell('A'.($filaObservacion + 1), '', 9),
        ], 44);
        $filas[] = $this->row($filaFirmas, [
            $this->inlineCell('A'.$filaFirmas, 'Nombre y firma operador Hidrocooler', 10),
            $this->inlineCell('F'.$filaFirmas, 'Nombre y firma Calidad', 10),
            $this->inlineCell('K'.$filaFirmas, 'Nombre y firma supervisor', 10),
        ], 34);
        $filas[] = $this->row($filaFirmas + 1, [
            $this->inlineCell('A'.($filaFirmas + 1), '', 9),
            $this->inlineCell('F'.($filaFirmas + 1), '', 9),
            $this->inlineCell('K'.($filaFirmas + 1), '', 9),
        ], 44);
        $filas[] = $this->row($ultimaFila, [
            $this->inlineCell(
                'A'.$ultimaFila,
                $enBlanco
                    ? 'Formulario en blanco generado por Estiba WMS para contingencia, trazabilidad y auditoría.'
                    : 'Registro generado por Estiba WMS desde ciclos trazables de Hidrocooler.',
                11,
            ),
        ], 20);

        $combinaciones = [
            'A1:J2', 'K1:L1', 'M1:O1', 'K2:L2', 'M2:O2', 'A3:J3', 'K3:L3', 'M3:O3',
            'A4:B4', 'C4:D4', 'E4:F4', 'G4:H4', 'I4:J4', 'L4:M4', 'N4:O4',
            'A5:B5', 'C5:D5', 'E5:F5', 'G5:O5', 'A6:O6',
            'A'.$filaObservacion.':O'.$filaObservacion,
            'A'.($filaObservacion + 1).':O'.($filaObservacion + 1),
            'A'.$filaFirmas.':E'.$filaFirmas, 'F'.$filaFirmas.':J'.$filaFirmas, 'K'.$filaFirmas.':O'.$filaFirmas,
            'A'.($filaFirmas + 1).':E'.($filaFirmas + 1), 'F'.($filaFirmas + 1).':J'.($filaFirmas + 1), 'K'.($filaFirmas + 1).':O'.($filaFirmas + 1),
            'A'.$ultimaFila.':O'.$ultimaFila,
        ];
        $mergeCells = implode('', array_map(
            fn (string $rango): string => '<mergeCell ref="'.$rango.'"/>',
            $combinaciones,
        ));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:O'.$ultimaFila.'"/>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols>'.$this->columns().'</cols>'
            .'<sheetData>'.implode('', $filas).'</sheetData>'
            .'<mergeCells count="'.count($combinaciones).'">'.$mergeCells.'</mergeCells>'
            .'<printOptions horizontalCentered="1"/>'
            .'<pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.15" footer="0.15"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddFooter>&amp;LEstiba WMS · Registro Hidrocooler&amp;RPágina &amp;P de &amp;N</oddFooter></headerFooter>'
            .'</worksheet>';
    }

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos
     * @return array{fecha:string,equipo:string,turno:string,operador:string,temporada:string}
     */
    private function contexto(Collection $procesos): array
    {
        return [
            'fecha' => $this->unico($procesos->map(fn ($proceso) => $proceso->inicio_at?->format('d-m-Y'))),
            'equipo' => $this->unico($procesos->pluck('equipo')),
            'turno' => $this->unico($procesos->pluck('turno')->map(
                fn ($turno) => $turno ? 'Turno '.$turno : null,
            )),
            'operador' => $this->unico($procesos->pluck('operador_snapshot')),
            'temporada' => $this->unico($procesos->map(
                fn ($proceso) => $proceso->lote?->temporada?->nombre,
            )),
        ];
    }

    private function unico(Collection $valores): string
    {
        $valores = $valores->filter(fn ($valor) => filled($valor))->unique()->values();

        return $valores->count() === 1 ? (string) $valores->first() : ($valores->isEmpty() ? '' : 'Según detalle');
    }

    /** @return array<int, string> */
    private function valores(ProcesoHidrocoolerMateriaPrima $proceso, int $numero): array
    {
        $lote = $proceso->lote;
        $recepcion = $lote?->recepcion;
        $observaciones = collect([
            $proceso->observacion_inicio,
            $proceso->observacion,
            $proceso->accion_correctiva ? 'Acción: '.$proceso->accion_correctiva : null,
        ])->filter()->implode("\n");

        return [
            (string) $numero,
            $proceso->inicio_at?->format('d-m-Y') ?? '',
            collect([$proceso->equipo, $proceso->turno ? 'Turno '.$proceso->turno : null])->filter()->implode("\n"),
            collect([$proceso->codigo, $lote?->numero_lote])->filter()->implode("\n"),
            collect([$recepcion?->numero_recepcion, $recepcion?->numero_guia_despacho])->filter()->implode("\n"),
            collect([$lote?->csg_snapshot, $lote?->cuartel, $lote?->variedad_snapshot])->filter()->implode(' · '),
            collect([
                $proceso->cantidad_envases_snapshot !== null ? $proceso->cantidad_envases_snapshot.' env.' : null,
                $proceso->kilos_netos_snapshot !== null ? $this->numero($proceso->kilos_netos_snapshot).' kg' : null,
            ])->filter()->implode("\n"),
            (string) ($proceso->cantidad_bombas_funcionando ?? ''),
            collect([
                $proceso->inicio_at?->format('H:i'),
                $proceso->termino_at?->format('H:i'),
                $proceso->duracion_minutos !== null ? $proceso->duracion_minutos.' min' : null,
            ])->filter()->implode(' · '),
            $this->temperaturas([
                $proceso->temperatura_inicial_c,
                $proceso->temperatura_objetivo_c,
                $proceso->temperatura_c,
            ]),
            $this->temperaturas([$proceso->temperatura_agua_inicial_c, $proceso->temperatura_agua_final_c]),
            collect([
                $proceso->cloro_libre_ppm !== null ? $this->numero($proceso->cloro_libre_ppm).' ppm' : null,
                $proceso->ph_agua !== null ? 'pH '.$this->numero($proceso->ph_agua) : null,
            ])->filter()->implode("\n"),
            collect([
                $this->etiqueta($proceso->condicion_visual_agua),
                $proceso->dosificador_operativo === null ? null : ($proceso->dosificador_operativo ? 'Dosif. operativo' : 'Dosif. no operativo'),
                $this->etiqueta($proceso->manejo_agua),
            ])->filter()->implode("\n"),
            $proceso->destino_salida === 'proceso' ? 'Directo a proceso' : ($proceso->destino_salida === 'camara' ? 'Cámara MP' : ''),
            $observaciones,
        ];
    }

    /** @param array<int, mixed> $valores */
    private function temperaturas(array $valores): string
    {
        return collect($valores)->map(
            fn ($valor) => $valor === null ? null : $this->numero($valor).' °C',
        )->filter()->implode(' · ');
    }

    private function etiqueta(?string $valor): ?string
    {
        return match ($valor) {
            'conforme' => 'Agua conforme',
            'no_conforme' => 'Agua no conforme',
            'sin_novedad' => 'Sin novedad',
            'filtrado' => 'Filtrado',
            'recambio' => 'Recambio',
            default => null,
        };
    }

    private function columns(): string
    {
        $anchos = [4, 10, 13, 16, 15, 22, 13, 8, 17, 18, 15, 13, 21, 13, 28];

        return implode('', array_map(
            fn (int $indice, int $ancho): string => '<col min="'.($indice + 1).'" max="'.($indice + 1).'" width="'.$ancho.'" customWidth="1"/>',
            array_keys($anchos),
            $anchos,
        ));
    }

    /** @param array<int, string> $celdas */
    private function row(int $fila, array $celdas, int $alto): string
    {
        return '<row r="'.$fila.'" ht="'.$alto.'" customHeight="1">'.implode('', $celdas).'</row>';
    }

    private function inlineCell(string $referencia, string $valor, int $estilo): string
    {
        return '<c r="'.$referencia.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->xml($valor).'</t></is></c>';
    }

    private function columna(int $indice): string
    {
        $columna = '';
        while ($indice > 0) {
            $indice--;
            $columna = chr(65 + ($indice % 26)).$columna;
            $indice = intdiv($indice, 26);
        }

        return $columna;
    }

    private function numero(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ''), '0'), '.');
    }

    private function xml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function rutaTemporal(string $prefijo, string $extension): string
    {
        $ruta = tempnam(sys_get_temp_dir(), $prefijo);
        if ($ruta === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal.');
        }
        if (is_file($ruta)) {
            unlink($ruta);
        }

        return $ruta.$extension;
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
    <HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Hojas de cálculo</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>
    <TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Registro Hidrocooler</vt:lpstr></vt:vector></TitlesOfParts>
</Properties>
XML;
    }

    private function coreProperties(string $titulo): string
    {
        $ahora = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($titulo).'</dc:title><dc:creator>Estiba WMS</dc:creator>'
            .'<cp:lastModifiedBy>Estiba WMS</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$ahora.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$ahora.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>
    <sheets><sheet name="Registro Hidrocooler" sheetId="1" r:id="rId1"/></sheets>
    <calcPr calcId="191029" fullCalcOnLoad="1" forceFullCalc="1"/>
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
    <fonts count="5">
        <font><sz val="8"/><name val="Aptos Narrow"/><family val="2"/></font>
        <font><b/><sz val="8"/><name val="Aptos Narrow"/><family val="2"/></font>
        <font><b/><sz val="18"/><name val="Aptos Narrow"/><family val="2"/></font>
        <font><b/><color rgb="FFFFFFFF"/><sz val="8"/><name val="Aptos Narrow"/><family val="2"/></font>
        <font><sz val="7"/><color rgb="FF5B6870"/><name val="Aptos Narrow"/><family val="2"/></font>
    </fonts>
    <fills count="6">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF0B5F73"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFDCEFF4"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFEAF6EC"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF6F8F9"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="3">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border><left style="thin"><color rgb="FF66757D"/></left><right style="thin"><color rgb="FF66757D"/></right><top style="thin"><color rgb="FF66757D"/></top><bottom style="thin"><color rgb="FF66757D"/></bottom><diagonal/></border>
        <border><left/><right/><top style="thin"><color rgb="FF66757D"/></top><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="12">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="top" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="2" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
    </cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML;
    }
}
