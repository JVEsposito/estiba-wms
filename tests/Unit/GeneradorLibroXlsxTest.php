<?php

namespace Tests\Unit;

use App\Services\Existencias\GeneradorLibroXlsx;
use Tests\TestCase;
use ZipArchive;

class GeneradorLibroXlsxTest extends TestCase
{
    public function test_genera_un_libro_valido_con_numeros_y_fechas_nativas(): void
    {
        $ruta = app(GeneradorLibroXlsx::class)->generar(
            'Existencia de prueba',
            [
                ['clave' => 'folio', 'titulo' => 'Folio'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'numero'],
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha'],
                ['clave' => 'fecha_hora', 'titulo' => 'Fecha hora', 'tipo' => 'fecha_hora'],
            ],
            [[
                'folio' => 'PAL-001',
                'cantidad' => 12.5,
                'fecha' => '2026-07-27',
                'fecha_hora' => '2026-07-27T10:30:00-04:00',
            ]],
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

        $estilos = $zip->getFromName('xl/styles.xml');
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($estilos);
        $this->assertIsString($hoja);
        $this->assertStringContainsString('numFmtId="164" formatCode="yyyy-mm-dd"', $estilos);
        $this->assertStringContainsString('numFmtId="165" formatCode="yyyy-mm-dd hh:mm"', $estilos);
        $this->assertStringContainsString('<c r="B7" s="4"><v>12.5</v></c>', $hoja);
        $this->assertMatchesRegularExpression('/<c r="C7" s="5"><v>[0-9.]+<\/v><\/c>/', $hoja);
        $this->assertMatchesRegularExpression('/<c r="D7" s="6"><v>[0-9.]+<\/v><\/c>/', $hoja);
        $this->assertStringContainsString('<autoFilter ref="A6:D7"/>', $hoja);
        $zip->close();
        @unlink($ruta);
    }
}
