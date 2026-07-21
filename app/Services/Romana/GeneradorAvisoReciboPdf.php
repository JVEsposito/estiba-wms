<?php

namespace App\Services\Romana;

use App\Enums\EstadoRecepcionRomana;
use App\Models\RecepcionRomana;
use DomainException;

class GeneradorAvisoReciboPdf
{
    public function generar(RecepcionRomana $recepcion): string
    {
        if ($recepcion->estado !== EstadoRecepcionRomana::Cerrado) {
            throw new DomainException('El Aviso de Recibo solo está disponible para recepciones cerradas.');
        }

        $lineas = [
            ['N° recepción', $recepcion->numero_recepcion],
            ['Ingreso', $recepcion->ingreso_at?->format('d-m-Y H:i')],
            ['Salida / destare', $recepcion->salida_at?->format('d-m-Y H:i')],
            ['Temporada', $recepcion->temporada_nombre_snapshot.' · '.$recepcion->temporada_codigo_snapshot],
            ['Cliente', $recepcion->cliente_nombre_snapshot],
            ['Código cliente', $recepcion->cliente_codigo_snapshot ?: 'Sin código externo'],
            ['Servicio', ucfirst($recepcion->tipo_servicio->value)],
            ['Guía de despacho', $recepcion->numero_guia_despacho],
            ['Envases declarados', $recepcion->cantidad_envases_declarados.' '.ucfirst($recepcion->tipo_envase_declarado->value)],
            ['Patente camión', $recepcion->patente_camion],
            ['Patente carro', $recepcion->patente_carro ?: 'No informada'],
            ['Conductor', $recepcion->nombre_conductor],
            ['RUT conductor', $recepcion->rut_conductor],
            ['Peso bruto', $this->peso($recepcion->peso_bruto).' kg'],
            ['Peso tara', $this->peso($recepcion->peso_tara).' kg'],
            ['PESO NETO', $this->peso($recepcion->peso_neto).' kg'],
        ];

        $contenido = "0.08 0.16 0.20 rg 0 770 595 72 re f\n";
        $contenido .= $this->texto(42, 810, 20, 'ESTIBA WMS', true, '1 1 1');
        $contenido .= $this->texto(42, 786, 12, 'AVISO DE RECIBO · ROMANA', false, '0.75 0.92 0.94');
        $contenido .= $this->texto(420, 807, 12, (string) $recepcion->numero_recepcion, true, '1 1 1');
        $contenido .= "0.15 0.72 0.70 RG 42 752 m 553 752 l S\n";
        $contenido .= $this->texto(42, 726, 11, 'Antecedentes contractuales de ingreso al frigorífico', true);

        $y = 695;
        foreach ($lineas as $indice => [$etiqueta, $valor]) {
            if ($indice === 13) {
                $contenido .= '0.92 0.96 0.97 rg 38 '.($y - 9)." 519 31 re f\n";
            }
            if ($indice === 15) {
                $contenido .= '0.08 0.50 0.48 rg 38 '.($y - 12)." 519 36 re f\n";
            }
            $color = $indice === 15 ? '1 1 1' : '0.15 0.20 0.23';
            $contenido .= $this->texto(48, $y, 9, (string) $etiqueta, $indice === 15, $color);
            $contenido .= $this->texto(235, $y, $indice === 15 ? 13 : 10, (string) $valor, true, $color);
            $y -= $indice >= 13 ? 35 : 29;
        }

        $contenido .= $this->texto(42, 222, 9, 'Observación de ingreso', true);
        $contenido .= $this->texto(42, 205, 9, $recepcion->observacion ?: 'Sin observaciones.');
        $contenido .= $this->texto(42, 180, 9, 'Observación de cierre', true);
        $contenido .= $this->texto(42, 163, 9, $recepcion->observacion_cierre ?: 'Sin observaciones.');
        $contenido .= "0.65 0.70 0.72 RG 42 122 m 240 122 l S 355 122 m 553 122 l S\n";
        $contenido .= $this->texto(78, 105, 8, 'Operador de romana');
        $contenido .= $this->texto(403, 105, 8, 'Transportista');
        $contenido .= $this->texto(42, 52, 7, 'Documento generado por Estiba WMS. Los pesos corresponden a los registros cerrados de la romana.');

        return $this->documento($contenido);
    }

    private function peso(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    private function texto(
        float $x,
        float $y,
        int $tamano,
        string $texto,
        bool $negrita = false,
        string $color = '0.15 0.20 0.23',
    ): string {
        $texto = function_exists('iconv')
            ? (iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) ?: $texto)
            : $texto;
        $texto = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $texto);
        $fuente = $negrita ? 'F2' : 'F1';

        return sprintf("%s rg BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $color, $fuente, $tamano, $x, $y, $texto);
    }

    private function documento(string $contenido): string
    {
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}endstream",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objetos as $indice => $objeto) {
            $offsets[] = strlen($pdf);
            $numero = $indice + 1;
            $pdf .= "{$numero} 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
