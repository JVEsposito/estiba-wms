<?php

namespace Tests\Unit;

use App\Services\Existencias\GeneradorLibroXlsx;
use Tests\TestCase;
use ZipArchive;

class GeneradorLibroXlsxTest extends TestCase
{
    public function test_genera_un_libro_valido_con_numeros_y_fechas_nativas(): void
    {
        $filas = (function (): \Generator {
            yield [
                'folio' => 'PAL-001',
                'cantidad' => 12.5,
                'fecha' => '2026-07-27',
                'fecha_hora' => '2026-07-27T10:30:00-04:00',
            ];
            yield [
                'folio' => 'PAL-002',
                'cantidad' => 8,
                'fecha' => '2026-07-28',
                'fecha_hora' => '2026-07-28T11:45:00-04:00',
            ];
        })();

        $ruta = app(GeneradorLibroXlsx::class)->generar(
            'Existencia de prueba',
            [
                ['clave' => 'folio', 'titulo' => 'Folio'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'numero'],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha'],
                ['clave' => 'fecha_hora', 'titulo' => 'Fecha hora', 'tipo' => 'fecha_hora'],
            ],
            $filas,
            [
                'fecha_corte' => '2026-07-27T10:30:00-04:00',
                'usuario' => 'Prueba',
                'temporada' => '2026-2027',
            ],
        );

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $this->assertNotFalse($zip->locateName('xl/styles.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));

        $estilos = $zip->getFromName('xl/styles.xml');
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($estilos);
        $this->assertIsString($hoja);
        $this->assertStringContainsString('numFmtId="164" formatCode="yyyy-mm-dd"', $estilos);
        $this->assertStringContainsString('numFmtId="165" formatCode="yyyy-mm-dd hh:mm"', $estilos);
        // Formato de base de datos: encabezados en la fila 1 y datos desde la fila 2.
        $this->assertStringContainsString('<c r="A1" t="inlineStr" s="2"><is><t xml:space="preserve">Folio</t></is></c>', $hoja);
        $this->assertStringContainsString('<c r="B2" s="4"><v>12.5</v></c>', $hoja);
        $this->assertMatchesRegularExpression('/<c r="C2" s="5"><v>[0-9.]+<\/v><\/c>/', $hoja);
        $this->assertMatchesRegularExpression('/<c r="D2" s="6"><v>[0-9.]+<\/v><\/c>/', $hoja);
        $this->assertStringContainsString('<c r="A3" t="inlineStr" s="3"><is><t xml:space="preserve">PAL-002</t></is></c>', $hoja);
        $this->assertStringContainsString('<autoFilter ref="A1:D3"/>', $hoja);
        $this->assertStringContainsString('<pane ySplit="1" topLeftCell="A2"', $hoja);
        $this->assertStringNotContainsString('Fecha de corte', $hoja);

        $corte = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertIsString($corte);
        $this->assertStringContainsString('Fecha de corte', $corte);
        $this->assertStringContainsString('<t xml:space="preserve">2</t>', $corte);
        $this->assertStringContainsString('<sheet name="Corte" sheetId="2" r:id="rId3"/>', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        @unlink($ruta);
    }
}
