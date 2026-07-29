<?php

namespace Tests\Unit;

use App\Services\Materiales\GeneradorEtiquetaMaterialNlbl;
use App\Services\Materiales\GeneradorEtiquetaMaterialPdf;
use App\Services\Materiales\GeneradorEtiquetaMaterialZpl;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GeneradorEtiquetaMaterialNlblTest extends TestCase
{
    public function test_mantiene_los_textos_dentro_de_una_etiqueta_bixolon_50_por_100_horizontal(): void
    {
        $formato = $this->generarFormato(
            [
                'fabricante' => 'Bixolon',
                'modelo' => 'SLP-TX400',
                'ancho_mm' => 50,
                'alto_mm' => 100,
                'orientacion' => 'horizontal',
            ],
            'qr',
        );

        $this->assertTextosDentroDelArea($formato, 100000, 50000);
        $this->assertStringContainsString('<Name>Código QR</Name>', $formato);
        $this->assertStringContainsString('<Name>BIXOLON SLP-TX400</Name>', $formato);
        $this->assertContenidoCompleto($formato);
        $this->assertMatchesRegularExpression(
            '#<Name>Folio</Name>.*?<Name>Arial</Name><Height>14</Height>#s',
            $formato,
        );
        $this->assertMatchesRegularExpression(
            '#<Name>Detalle 2</Name>.*?<Name>Arial</Name><Height>6</Height>#s',
            $formato,
        );
    }

    public function test_ajusta_el_folio_en_una_bixolon_50_por_100_vertical(): void
    {
        $formato = $this->generarFormato(
            [
                'fabricante' => 'Bixolon',
                'modelo' => 'SLP-TX400',
                'ancho_mm' => 100,
                'alto_mm' => 50,
                'orientacion' => 'vertical',
            ],
            'qr',
        );

        $this->assertTextosDentroDelArea($formato, 50000, 100000);
        $this->assertStringContainsString('<Name>Código QR</Name>', $formato);
        $this->assertContenidoCompleto($formato);
        $this->assertMatchesRegularExpression(
            '#<Name>Folio</Name>.*?<Name>Arial</Name><Height>10</Height>#s',
            $formato,
        );
    }

    public function test_code128_incluye_todos_los_datos_en_una_bixolon_50_por_100(): void
    {
        $formato = $this->generarFormato(
            [
                'fabricante' => 'Bixolon',
                'modelo' => 'SLP-TX400',
                'ancho_mm' => 50,
                'alto_mm' => 100,
                'orientacion' => 'horizontal',
            ],
            'code128',
        );

        $this->assertTextosDentroDelArea($formato, 100000, 50000);
        $this->assertStringContainsString('<Name>Código Code 128</Name>', $formato);
        $this->assertContenidoCompleto($formato);
        preg_match_all('#<Name>Detalle \\d+</Name>#', $formato, $detalles);
        $this->assertCount(6, $detalles[0]);
    }

    public function test_mantiene_los_textos_dentro_de_una_etiqueta_bixolon_100_por_200_vertical(): void
    {
        $formato = $this->generarFormato(
            [
                'fabricante' => 'Bixolon',
                'modelo' => 'SLP-TX400',
                'ancho_mm' => 100,
                'alto_mm' => 200,
                'orientacion' => 'vertical',
            ],
            'code128',
        );

        $this->assertTextosDentroDelArea($formato, 100000, 200000);
        $this->assertStringContainsString('<Name>Código Code 128</Name>', $formato);
        $this->assertStringContainsString('<Name>BIXOLON SLP-TX400</Name>', $formato);
        $this->assertContenidoCompleto($formato);
    }

    public function test_pdf_y_zpl_incluyen_los_mismos_datos_completos(): void
    {
        $etiqueta = $this->etiqueta();
        $perfil = [
            'fabricante' => 'Zebra',
            'modelo' => 'ZT231',
            'dpi' => 203,
            'ancho_mm' => 100,
            'alto_mm' => 200,
            'orientacion' => 'vertical',
        ];
        $contenidos = [
            'MCCJ01 · CAJA PLÁSTICA 30X50X150',
            'Proveedor: MULTIFRUTA S.A',
            'Fecha recepción: 29/07/2026 13:14',
        ];

        $pdf = (new GeneradorEtiquetaMaterialPdf)->generar([$etiqueta], $perfil);
        foreach ($contenidos as $esperado) {
            $esperadoPdf = iconv('UTF-8', 'Windows-1252//TRANSLIT', $esperado)
                ?: $esperado;
            $this->assertStringContainsString($esperadoPdf, $pdf);
        }

        foreach (['qr', 'code128'] as $simbologia) {
            $zpl = (new GeneradorEtiquetaMaterialZpl)->generar(
                [$etiqueta],
                $perfil,
                simbologia: $simbologia,
            );
            foreach ($contenidos as $esperado) {
                $this->assertStringContainsString($esperado, $zpl);
            }
            $this->assertStringNotContainsString('…', $zpl);
        }
    }

    /**
     * @param  array<string, mixed>  $perfil
     */
    private function generarFormato(array $perfil, string $simbologia): string
    {
        $metodo = new ReflectionMethod(GeneradorEtiquetaMaterialNlbl::class, 'formato');
        $formato = $metodo->invoke(
            new GeneradorEtiquetaMaterialNlbl,
            $this->etiqueta(),
            $perfil,
            $simbologia,
            'Etiqueta de prueba',
        );

        $this->assertIsString($formato);

        return $formato;
    }

    /** @return array<string, mixed> */
    private function etiqueta(): array
    {
        return [
            'numero_folio' => 'FGE0000001',
            'cliente_codigo' => 'MC-001',
            'cliente_nombre' => 'MACE',
            'item_codigo' => 'MCCJ01',
            'item_nombre' => 'CAJA PLÁSTICA 30X50X150',
            'cantidad' => '680.000',
            'unidad_medida' => 'unidad',
            'origen' => 'recepcion',
            'numero_guia' => '147092',
            'lote_proveedor' => null,
            'proveedor_nombre' => 'MULTIFRUTA S.A',
            'fecha_recepcion' => '29/07/2026 13:14',
            'bloqueado' => false,
            'motivo_bloqueo' => null,
        ];
    }

    private function assertContenidoCompleto(string $formato): void
    {
        foreach ([
            'Folio: FGE0000001',
            'MCCJ01 · CAJA PLÁSTICA 30X50X150',
            'Proveedor: MULTIFRUTA S.A',
            'Fecha recepción: 29/07/2026 13:14',
        ] as $contenido) {
            $this->assertStringContainsString(
                '<FixedContents Base64Encoded="true">'.base64_encode($contenido).'</FixedContents>',
                $formato,
            );
        }

        preg_match_all(
            '#<FixedContents Base64Encoded="true">([^<]+)</FixedContents>#',
            $formato,
            $contenidos,
        );
        $decodificados = array_map(
            fn (string $valor): string => (string) base64_decode($valor, true),
            $contenidos[1],
        );
        $this->assertStringNotContainsString('…', implode("\n", $decodificados));
    }

    private function assertTextosDentroDelArea(
        string $formato,
        int $anchoEsperado,
        int $altoEsperado,
    ): void {
        $resultadoMedia = preg_match(
            '#<Media Type="FormatMedia">.*?<Width>(\d+)</Width>\s*<Height>(\d+)</Height>#s',
            $formato,
            $media,
        );
        $this->assertSame(1, $resultadoMedia);
        $this->assertSame($anchoEsperado, (int) $media[1]);
        $this->assertSame($altoEsperado, (int) $media[2]);

        $resultadoTextos = preg_match_all(
            '#<Geometry Type="RectGeometry"><Width>(\d+)</Width><Height>(\d+)</Height><Left>(\d+)</Left><Top>(\d+)</Top><AnchoringPoint>4</AnchoringPoint></Geometry>#',
            $formato,
            $geometrias,
            PREG_SET_ORDER,
        );
        $this->assertNotFalse($resultadoTextos);

        $textos = array_values(array_filter(
            $geometrias,
            fn (array $geometria): bool => (int) $geometria[1] > 0
                && (int) $geometria[2] > 0,
        ));
        $this->assertGreaterThanOrEqual(6, count($textos));

        foreach ($textos as $geometria) {
            $ancho = (int) $geometria[1];
            $alto = (int) $geometria[2];
            $centroX = (int) $geometria[3];
            $centroY = (int) $geometria[4];

            $this->assertGreaterThanOrEqual(0, ($centroX * 2) - $ancho);
            $this->assertLessThanOrEqual($anchoEsperado * 2, ($centroX * 2) + $ancho);
            $this->assertGreaterThanOrEqual(0, ($centroY * 2) - $alto);
            $this->assertLessThanOrEqual($altoEsperado * 2, ($centroY * 2) + $alto);
        }
    }
}
