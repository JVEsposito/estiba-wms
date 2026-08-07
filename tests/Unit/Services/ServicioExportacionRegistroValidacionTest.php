<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\ValidacionPallet;
use App\Services\Validacion\ServicioExportacionRegistroValidacion;
use Illuminate\Support\Collection;
use Tests\TestCase;
use ZipArchive;

class ServicioExportacionRegistroValidacionTest extends TestCase
{
    public function test_crea_hojas_adicionales_y_deja_observados_fuera_de_aceptado_rechazado(): void
    {
        $usuario = User::factory()->make(['name' => 'Validadora de prueba']);
        $usuario->id = 77;
        $validaciones = new Collection;

        foreach (range(1, 21) as $numero) {
            $resultado = $numero === 21 ? 'observado' : 'aprobado';
            $validacion = new ValidacionPallet;
            $validacion->forceFill([
                'numero_folio' => sprintf('PAL-%04d', $numero),
                'numero_intento' => 1,
                'cantidad_cajas' => 10,
                'linea_proceso' => 3,
                'turno' => 'A',
                'resultado' => $resultado,
                'motivo' => $numero === 21 ? 'etiqueta_no_coincide' : null,
                'observacion' => $numero === 21 ? 'Revisar impresión física.' : null,
                'user_id' => 77,
                'generado_dispositivo_at' => sprintf('2026-07-29T12:%02d:00-04:00', $numero),
                'snapshot' => [
                    'articulo' => [
                        'especie' => 'Cereza',
                        'variedad' => 'Santina',
                        'calibre' => '2J',
                        'envase' => '5 kg',
                    ],
                    'origen' => [
                        'cliente' => 'DIS',
                        'marca' => 'ATLAS',
                        'csg' => '105410',
                        'predio' => 'Predio prueba',
                    ],
                    'categoria' => [
                        'nombre' => $numero === 21 ? 'CAT-1' : 'CAT1',
                        'codigo_externo' => null,
                    ],
                ],
            ]);
            $validacion->setRelation('usuario', $usuario);
            $validaciones->push($validacion);
        }

        $ruta = app(ServicioExportacionRegistroValidacion::class)->generar($validaciones);
        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($ruta) === true);
            $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
            $this->assertNotFalse($zip->locateName('xl/drawings/drawing2.xml'));
            $libro = $zip->getFromName('xl/workbook.xml');
            $this->assertIsString($libro);
            $this->assertStringContainsString('sheet2.xml', $zip->getFromName('xl/_rels/workbook.xml.rels'));

            $segundaHoja = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertIsString($segundaHoja);
            $this->assertSame('Categoría', $this->valorCelda($segundaHoja, 'M10'));
            $this->assertSame('PAL-0021', $this->valorCelda($segundaHoja, 'B11'));
            $this->assertSame('', $this->valorCelda($segundaHoja, 'J11'));
            $this->assertSame('', $this->valorCelda($segundaHoja, 'K11'));
            $this->assertSame(
                'OBSERVADO: Etiqueta no coincide — Revisar impresión física.',
                $this->valorCelda($segundaHoja, 'L11'),
            );
            $this->assertSame('CAT-1', $this->valorCelda($segundaHoja, 'M11'));
            $this->assertSame('10', $this->valorCelda($segundaHoja, 'I31'));
        } finally {
            $zip->close();
            @unlink($ruta);
        }
    }

    private function valorCelda(string $xml, string $referencia): string
    {
        $documento = new \DOMDocument;
        $this->assertTrue($documento->loadXML($xml));
        $xpath = new \DOMXPath($documento);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $celda = $xpath->query("//m:c[@r='{$referencia}']")->item(0);
        $this->assertNotNull($celda);
        $inline = $xpath->query('m:is/m:t', $celda)->item(0);

        return $inline?->textContent ?? $xpath->query('m:v', $celda)->item(0)?->textContent ?? '';
    }
}
