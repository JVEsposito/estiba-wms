<?php

namespace App\Services\MateriaPrima;

use App\Models\ProcesoHidrocoolerMateriaPrima;
use Illuminate\Support\Collection;

class RegistroHidrocoolerPdf
{
    private const FILAS_POR_PAGINA = 7;

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos */
    public function generar(Collection $procesos): string
    {
        return $this->documento($this->paginas($procesos->values(), false));
    }

    public function generarEnBlanco(): string
    {
        return $this->documento($this->paginas(collect(), true));
    }

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos
     *  @return array<int, string>
     */
    private function paginas(Collection $procesos, bool $enBlanco): array
    {
        $filas = $enBlanco
            ? collect(range(1, 10))->map(fn (int $numero): array => $this->filaVacia($numero))
            : $procesos->values()->map(
                fn (ProcesoHidrocoolerMateriaPrima $proceso, int $indice): array => $this->valores($proceso, $indice + 1),
            );
        if ($filas->isEmpty()) {
            $filas = collect([$this->filaVacia(1)]);
        }
        $grupos = $filas->chunk(self::FILAS_POR_PAGINA)->values();
        $contexto = $this->contexto($procesos);
        $paginas = [];

        foreach ($grupos as $indice => $grupo) {
            $paginas[] = $this->pagina(
                $grupo->values(),
                $contexto,
                $indice + 1,
                $grupos->count(),
                $enBlanco,
                $indice === $grupos->count() - 1,
            );
        }

        return $paginas;
    }

    /** @param Collection<int, array<int, string>> $filas
     *  @param array{fecha:string,equipo:string,turno:string,operador:string,temporada:string} $contexto
     */
    private function pagina(
        Collection $filas,
        array $contexto,
        int $pagina,
        int $totalPaginas,
        bool $enBlanco,
        bool $ultima,
    ): string {
        $contenido = "0.16 0.22 0.25 RG 0.7 w\n";
        $contenido .= "20 525 802 50 re S\n";
        $contenido .= "650 525 m 650 575 l S 720 525 m 720 575 l S\n";
        $contenido .= "650 542 m 822 542 l S 650 559 m 822 559 l S\n";
        $contenido .= $this->texto(35, 551, 16, 'REGISTRO DE CONTROL DE HIDROCOOLER', true, '0.04 0.28 0.35');
        $contenido .= $this->texto(35, 535, 7, 'Control operacional y PCC - trazabilidad por lote y ciclo');
        $contenido .= $this->texto(656, 565, 6, 'CODIGO', true);
        $contenido .= $this->texto(728, 565, 6, 'POR DEFINIR');
        $contenido .= $this->texto(656, 548, 6, 'VERSION', true);
        $contenido .= $this->texto(728, 548, 6, '0');
        $contenido .= $this->texto(656, 531, 6, 'FECHA', true);
        $contenido .= $this->texto(728, 531, 6, $contexto['fecha']);

        $contenido .= $this->campo(20, 505, 48, 17, 'Fecha', $contexto['fecha']);
        $contenido .= $this->campo(155, 505, 52, 17, 'Equipo', $contexto['equipo']);
        $contenido .= $this->campo(322, 505, 44, 17, 'Turno', $contexto['turno']);
        $contenido .= $this->campo(458, 505, 54, 17, 'Operador', $contexto['operador']);
        $contenido .= $this->campo(650, 505, 62, 17, 'Temporada', $contexto['temporada']);

        $contenido .= "0.91 0.96 0.97 rg 20 474 802 24 re f\n0.33 0.48 0.53 RG 20 474 802 24 re S\n";
        $contenido .= $this->texto(
            27,
            489,
            6,
            'Referencia: cloro libre 80-120 ppm y pH 6-7, o rango vigente validado. Ante desviacion: detener, ajustar, recircular o renovar agua, retener fruta y documentar la accion.',
            false,
            '0.11 0.31 0.36',
        );
        $contenido .= $this->texto(27, 479, 5.5, 'Frecuencia minima: inicio, durante proceso, cambio de lote y recambio de agua.');

        $anchos = [24, 45, 54, 50, 58, 72, 50, 36, 55, 56, 45, 42, 58, 42, 95];
        $encabezados = [
            'N', 'Fecha', "Equipo\nTurno", "Ciclo\nLote", "Recep.\nGuia", "CSG - Cuartel\nVariedad",
            "Envases\nKg", 'Bombas', "Inicio - Termino\nMin", "T fruta\nIni - Obj - Fin",
            "T agua\nIni - Fin", "Cloro\npH", "Agua - Dosif.\nControl", 'Destino', "Observaciones\nAccion correctiva",
        ];
        $x = 30;
        $altoEncabezado = 34;
        $yEncabezado = 438;
        foreach ($encabezados as $columna => $encabezado) {
            $ancho = $anchos[$columna];
            $contenido .= "0.04 0.37 0.45 rg {$x} {$yEncabezado} {$ancho} {$altoEncabezado} re f\n";
            $contenido .= "0.22 0.34 0.38 RG {$x} {$yEncabezado} {$ancho} {$altoEncabezado} re S\n";
            $contenido .= $this->textoCelda($x, $yEncabezado, $ancho, $altoEncabezado, $encabezado, 5.2, true, '1 1 1', 3);
            $x += $ancho;
        }

        $altoFila = 42;
        foreach ($filas as $indice => $valores) {
            $y = $yEncabezado - (($indice + 1) * $altoFila);
            $x = 30;
            foreach ($valores as $columna => $valor) {
                $ancho = $anchos[$columna];
                if ($indice % 2 === 1) {
                    $contenido .= "0.97 0.98 0.98 rg {$x} {$y} {$ancho} {$altoFila} re f\n";
                }
                $contenido .= "0.42 0.50 0.53 RG {$x} {$y} {$ancho} {$altoFila} re S\n";
                $contenido .= $this->textoCelda(
                    $x,
                    $y,
                    $ancho,
                    $altoFila,
                    $valor,
                    in_array($columna, [5, 12, 14], true) ? 4.6 : 5,
                    false,
                    '0.12 0.18 0.20',
                    4,
                );
                $x += $ancho;
            }
        }

        if ($ultima) {
            $contenido .= $this->texto(30, 116, 6, 'OBSERVACIONES GENERALES / DESVIACIONES / RETENCION DE PRODUCTO', true);
            $contenido .= "0.42 0.50 0.53 RG 30 82 782 28 re S\n";
            $contenido .= "0.42 0.50 0.53 RG 30 48 m 230 48 l S 321 48 m 521 48 l S 612 48 m 812 48 l S\n";
            $contenido .= $this->texto(72, 36, 5.5, 'Operador Hidrocooler');
            $contenido .= $this->texto(390, 36, 5.5, 'Calidad');
            $contenido .= $this->texto(678, 36, 5.5, 'Supervisor');
        }

        $pie = $enBlanco
            ? 'Formulario en blanco generado por Estiba WMS para contingencia, trazabilidad y auditoria.'
            : 'Registro generado por Estiba WMS desde ciclos trazables de Hidrocooler.';
        $contenido .= $this->texto(30, 16, 5, $pie, false, '0.35 0.40 0.42');
        $contenido .= $this->texto(758, 16, 5, "Pagina {$pagina} de {$totalPaginas}", false, '0.35 0.40 0.42');

        return $contenido;
    }

    private function campo(float $x, float $y, float $anchoEtiqueta, float $alto, string $etiqueta, string $valor): string
    {
        $anchoTotal = match ($etiqueta) {
            'Fecha' => 125,
            'Equipo' => 157,
            'Turno' => 126,
            'Operador' => 182,
            default => 172,
        };
        $contenido = "0.33 0.48 0.53 RG {$x} {$y} {$anchoTotal} {$alto} re S\n";
        $contenido .= "0.91 0.96 0.97 rg {$x} {$y} {$anchoEtiqueta} {$alto} re f\n";
        $contenido .= $this->texto($x + 4, $y + 6, 5.5, $etiqueta, true);
        $contenido .= $this->texto($x + $anchoEtiqueta + 4, $y + 6, 5.5, $valor);

        return $contenido;
    }

    /** @param Collection<int, ProcesoHidrocoolerMateriaPrima> $procesos
     *  @return array{fecha:string,equipo:string,turno:string,operador:string,temporada:string}
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

        return $valores->count() === 1 ? (string) $valores->first() : ($valores->isEmpty() ? '' : 'Segun detalle');
    }

    /** @return array<int, string> */
    private function filaVacia(int $numero): array
    {
        $fila = array_fill(0, 15, '');
        $fila[0] = (string) $numero;

        return $fila;
    }

    /** @return array<int, string> */
    private function valores(ProcesoHidrocoolerMateriaPrima $proceso, int $numero): array
    {
        $lote = $proceso->lote;
        $recepcion = $lote?->recepcion;

        return [
            (string) $numero,
            $proceso->inicio_at?->format('d-m-Y') ?? '',
            collect([$proceso->equipo, $proceso->turno ? 'Turno '.$proceso->turno : null])->filter()->implode("\n"),
            collect([$proceso->codigo, $lote?->numero_lote])->filter()->implode("\n"),
            collect([$recepcion?->numero_recepcion, $recepcion?->numero_guia_despacho])->filter()->implode("\n"),
            collect([$lote?->csg_snapshot, $lote?->cuartel, $lote?->variedad_snapshot])->filter()->implode(' - '),
            collect([
                $proceso->cantidad_envases_snapshot !== null ? $proceso->cantidad_envases_snapshot.' env.' : null,
                $proceso->kilos_netos_snapshot !== null ? $this->numero($proceso->kilos_netos_snapshot).' kg' : null,
            ])->filter()->implode("\n"),
            (string) ($proceso->cantidad_bombas_funcionando ?? ''),
            collect([
                $proceso->inicio_at?->format('H:i'),
                $proceso->termino_at?->format('H:i'),
                $proceso->duracion_minutos !== null ? $proceso->duracion_minutos.' min' : null,
            ])->filter()->implode(' - '),
            $this->temperaturas([$proceso->temperatura_inicial_c, $proceso->temperatura_objetivo_c, $proceso->temperatura_c]),
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
            $proceso->destino_salida === 'proceso' ? 'A proceso' : ($proceso->destino_salida === 'camara' ? 'Camara MP' : ''),
            collect([
                $proceso->observacion_inicio,
                $proceso->observacion,
                $proceso->accion_correctiva ? 'Accion: '.$proceso->accion_correctiva : null,
            ])->filter()->implode("\n"),
        ];
    }

    /** @param array<int, mixed> $valores */
    private function temperaturas(array $valores): string
    {
        return collect($valores)->map(
            fn ($valor) => $valor === null ? null : $this->numero($valor).' C',
        )->filter()->implode(' - ');
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

    private function numero(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 3, ',', '.'), '0'), ',');
    }

    private function textoCelda(
        float $x,
        float $y,
        float $ancho,
        float $alto,
        string $texto,
        float $tamano,
        bool $negrita,
        string $color,
        int $maximoLineas,
    ): string {
        $lineas = $this->envolver($texto, $ancho, $tamano, $maximoLineas);
        $salto = $tamano + 1.6;
        $inicioY = $y + $alto - $salto - 2;
        $contenido = '';
        foreach ($lineas as $indice => $linea) {
            $contenido .= $this->texto($x + 2.5, $inicioY - ($indice * $salto), $tamano, $linea, $negrita, $color);
        }

        return $contenido;
    }

    /** @return array<int, string> */
    private function envolver(string $texto, float $ancho, float $tamano, int $maximo): array
    {
        if ($texto === '') {
            return [];
        }
        $caracteres = max(3, (int) floor(($ancho - 5) / max(2.5, $tamano * 0.52)));
        $lineas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            foreach (explode("\n", wordwrap(trim($linea), $caracteres, "\n", true)) as $segmento) {
                if ($segmento !== '') {
                    $lineas[] = $segmento;
                }
            }
        }
        if (count($lineas) > $maximo) {
            $lineas = array_slice($lineas, 0, $maximo);
            $lineas[$maximo - 1] = rtrim($lineas[$maximo - 1], '.').'...';
        }

        return $lineas;
    }

    private function texto(
        float $x,
        float $y,
        float $tamano,
        string $texto,
        bool $negrita = false,
        string $color = '0.12 0.18 0.20',
    ): string {
        $texto = function_exists('iconv')
            ? (iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) ?: $texto)
            : $texto;
        $texto = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $texto);
        $fuente = $negrita ? 'F2' : 'F1';

        return "BT /{$fuente} {$tamano} Tf {$color} rg {$x} {$y} Td ({$texto}) Tj ET\n";
    }

    /** @param array<int, string> $paginas */
    private function documento(array $paginas): string
    {
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $hijos = [];
        foreach ($paginas as $indice => $contenido) {
            $paginaObjeto = 5 + ($indice * 2);
            $contenidoObjeto = $paginaObjeto + 1;
            $hijos[] = $paginaObjeto.' 0 R';
            $objetos[$paginaObjeto] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contenidoObjeto.' 0 R >>';
            $objetos[$contenidoObjeto] = '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}endstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $hijos).'] /Count '.count($hijos).' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objetos as $numero => $objeto) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero." 0 obj\n".$objeto."\nendobj\n";
        }
        $xref = strlen($pdf);
        $cantidad = max(array_keys($objetos)) + 1;
        $pdf .= "xref\n0 {$cantidad}\n0000000000 65535 f \n";
        for ($numero = 1; $numero < $cantidad; $numero++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$numero])."\n";
        }
        $pdf .= "trailer\n<< /Size {$cantidad} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
