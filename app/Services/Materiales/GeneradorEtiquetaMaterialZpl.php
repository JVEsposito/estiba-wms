<?php

namespace App\Services\Materiales;

class GeneradorEtiquetaMaterialZpl
{
    /**
     * @param  array<int, array<string, mixed>>  $etiquetas
     * @param  array<string, mixed>  $perfil
     */
    public function generar(array $etiquetas, array $perfil, int $copias = 1): string
    {
        $dpi = (int) $perfil['dpi'];
        $ancho = $this->puntos((float) $perfil['ancho_mm'], $dpi);
        $alto = $this->puntos((float) $perfil['alto_mm'], $dpi);
        $margen = max(12, (int) round($ancho * 0.035));
        $contenido = '';

        foreach ($etiquetas as $etiqueta) {
            for ($copia = 0; $copia < $copias; $copia++) {
                $contenido .= "^XA\n";
                $contenido .= "^CI28\n";
                $contenido .= "^PW{$ancho}\n^LL{$alto}\n^LH0,0\n";
                $contenido .= "^FO0,0^GB{$ancho},".max(35, (int) round($alto * 0.17)).",35^FS\n";
                $contenido .= $this->texto(
                    $margen,
                    max(5, (int) round($alto * 0.035)),
                    max(24, (int) round($alto * 0.095)),
                    max(18, (int) round($alto * 0.075)),
                    (string) $etiqueta['numero_folio'],
                    inverso: true,
                );

                $barcodeY = max(48, (int) round($alto * 0.20));
                $barcodeHeight = max(38, (int) round($alto * 0.24));
                $moduleWidth = $dpi >= 300 ? 3 : 2;
                $contenido .= sprintf(
                    "^BY%d,2,%d^FO%d,%d^BCN,%d,Y,N,N^FH\\^FD%s^FS\n",
                    $moduleWidth,
                    $barcodeHeight,
                    $margen,
                    $barcodeY,
                    $barcodeHeight,
                    $this->campo((string) $etiqueta['numero_folio']),
                );

                $lineY = $barcodeY + $barcodeHeight + max(38, (int) round($alto * 0.10));
                $fontHeight = max(17, (int) round($alto * 0.055));
                $lineHeight = max(22, (int) round($alto * 0.072));
                $lineas = [
                    $etiqueta['cliente_codigo'].' · '.$etiqueta['cliente_nombre'],
                    $etiqueta['item_codigo'].' · '.$etiqueta['item_nombre'],
                    'Cantidad: '.$etiqueta['cantidad'].' '.$etiqueta['unidad_medida'],
                    ...$this->lineasOrigen($etiqueta),
                ];
                if ($etiqueta['bloqueado']) {
                    $lineas[] = 'BLOQUEADO: '.($etiqueta['motivo_bloqueo'] ?: 'Sin motivo');
                }

                foreach ($lineas as $indice => $linea) {
                    $y = $lineY + ($indice * $lineHeight);
                    if ($y + $fontHeight >= $alto - 5) {
                        break;
                    }
                    $contenido .= $this->texto(
                        $margen,
                        $y,
                        $fontHeight,
                        max(14, (int) round($fontHeight * 0.82)),
                        $this->recortar((string) $linea, max(20, (int) floor($ancho / ($fontHeight * 0.62)))),
                    );
                }

                $contenido .= "^PQ1,0,1,N\n^XZ\n";
            }
        }

        return $contenido;
    }

    private function puntos(float $milimetros, int $dpi): int
    {
        return max(1, (int) round(($milimetros / 25.4) * $dpi));
    }

    /**
     * @param  array<string, mixed>  $etiqueta
     * @return array<int, string>
     */
    private function lineasOrigen(array $etiqueta): array
    {
        if (($etiqueta['origen'] ?? 'recepcion') === 'transformacion') {
            return [
                'Transformación: '.$etiqueta['orden_transformacion']
                    .' · Lote '.$etiqueta['numero_lote_transformacion'],
                'Lote material: '.($etiqueta['lote_proveedor'] ?: '—'),
            ];
        }

        return [
            'Guía: '.$etiqueta['numero_guia'].' · Lote: '.($etiqueta['lote_proveedor'] ?: '—'),
            'Proveedor: '.$etiqueta['proveedor_nombre'],
        ];
    }

    private function texto(
        int $x,
        int $y,
        int $alto,
        int $ancho,
        string $texto,
        bool $inverso = false,
    ): string {
        return sprintf(
            "^FO%d,%d^A0N,%d,%d%s^FH\\^FD%s^FS\n",
            $x,
            $y,
            $alto,
            $ancho,
            $inverso ? '^FR' : '',
            $this->campo($texto),
        );
    }

    private function campo(string $valor): string
    {
        return str_replace(
            ['\\', '^', '~', "\r", "\n"],
            ['\\5C', '\\5E', '\\7E', ' ', ' '],
            $valor,
        );
    }

    private function recortar(string $texto, int $maximo): string
    {
        if (mb_strlen($texto) <= $maximo) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, max(1, $maximo - 1))).'…';
    }
}
