<?php

namespace App\Services\MateriaPrima;

use App\Models\DefectoRecepcionMp;
use Illuminate\Support\Collection;

class RegistroDefectosRecepcionPdf
{
    /** @param Collection<int, DefectoRecepcionMp> $defectos */
    public function generar(Collection $defectos, string $temporada): string
    {
        $paginas = [];
        foreach ($defectos as $indice => $defecto) {
            $contenido = $this->texto(40, 790, 17, 'DEFECTOS DE RECEPCIÓN', true);
            $contenido .= $this->texto(40, 765, 10, "Temporada: {$temporada} · Registro ".($indice + 1).' de '.$defectos->count());
            $campos = [
                'Fecha' => $defecto->registrado_at?->format('d-m-Y H:i') ?? '—',
                'Recepción' => $defecto->numero_recepcion_snapshot ?: '—',
                'Guía de despacho' => $defecto->numero_guia_snapshot ?: '—',
                'Cliente' => $defecto->cliente_nombre_snapshot ?: '—',
                'Categoría' => str_replace('_', ' ', $defecto->categoria),
                'Envase' => $defecto->tipo_envase ?: '—',
                'Cantidad afectada' => (string) ($defecto->cantidad_afectada ?? '—'),
                'Validador' => $defecto->validador?->name ?: '—',
                'Tablet' => $defecto->dispositivo?->codigo ?: '—',
                'Identificador' => $defecto->id,
            ];
            $y = 735;
            foreach ($campos as $etiqueta => $valor) {
                $contenido .= $this->texto(40, $y, 10, $etiqueta.':', true);
                $contenido .= $this->texto(170, $y, 10, $valor);
                $y -= 22;
            }
            $contenido .= $this->texto(40, $y - 10, 11, 'Descripción del defecto', true);
            $y -= 30;
            foreach ($this->lineas($defecto->descripcion, 98) as $linea) {
                $contenido .= $this->texto(40, $y, 10, $linea);
                $y -= 15;
            }
            $y -= 15;
            $contenido .= $this->texto(40, $y, 11, 'Evidencias (descargar imágenes en el ZIP)', true);
            foreach ($defecto->evidencias as $evidencia) {
                $y -= 17;
                $contenido .= $this->texto(40, $y, 9, strtoupper($evidencia->tipo).' · '.$evidencia->id);
            }
            $contenido .= $this->texto(40, 35, 9, 'FoliOS · Registro de auditoría generado '.now()->format('d-m-Y H:i'));
            $paginas[] = $contenido;
        }
        if ($paginas === []) {
            $paginas[] = $this->texto(40, 790, 17, 'DEFECTOS DE RECEPCIÓN', true)
                .$this->texto(40, 760, 10, "Temporada: {$temporada}")
                .$this->texto(40, 735, 10, 'No hay registros que coincidan con los filtros.');
        }

        return $this->documento($paginas);
    }

    /** @return array<int, string> */
    private function lineas(string $texto, int $maximo): array
    {
        $lineas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', trim($parrafo)) ?: [] as $palabra) {
                foreach (mb_str_split($palabra, $maximo) as $parte) {
                    if (mb_strlen($actual.' '.$parte) > $maximo && $actual !== '') {
                        $lineas[] = $actual;
                        $actual = '';
                    }
                    $actual .= ($actual === '' ? '' : ' ').$parte;
                }
            }
            $lineas[] = $actual;
        }

        return $lineas;
    }

    private function texto(int $x, int $y, int $tamano, string $texto, bool $negrita = false): string
    {
        $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto) ?: '';
        $texto = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $texto);
        $fuente = $negrita ? 'F2' : 'F1';

        return "BT /{$fuente} {$tamano} Tf 0.10 0.18 0.23 rg {$x} {$y} Td ({$texto}) Tj ET\n";
    }

    /** @param array<int, string> $paginas */
    private function documento(array $paginas): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $hijos = [];
        foreach ($paginas as $indice => $contenido) {
            $pagina = 5 + $indice * 2;
            $flujo = $pagina + 1;
            $hijos[] = "{$pagina} 0 R";
            $objetos[$pagina] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$flujo} 0 R >>";
            $objetos[$flujo] = '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}endstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $hijos).'] /Count '.count($hijos).' >>';
        ksort($objetos);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objetos as $numero => $objeto) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$objeto}\nendobj\n";
        }
        $xref = strlen($pdf);
        $cantidad = max(array_keys($objetos)) + 1;
        $pdf .= "xref\n0 {$cantidad}\n0000000000 65535 f \n";
        for ($numero = 1; $numero < $cantidad; $numero++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$numero])."\n";
        }

        return $pdf."trailer\n<< /Size {$cantidad} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
