<?php

namespace Tests\Feature\Architecture;

use App\Services\Transiciones\MotorTransicionesOperacionales;
use Tests\TestCase;

class MotorTransicionesArchitectureTest extends TestCase
{
    public function test_los_flujos_criticos_participan_del_motor_central(): void
    {
        $servicios = [
            'Estiba/ServicioMovimientoEstiba.php',
            'Validacion/ServicioValidacionPallet.php',
            'Validacion/ServicioAnulacionValidacionPallet.php',
            'Validacion/ServicioCorreccionValidacionPallet.php',
            'Validacion/ServicioRepaletizaje.php',
            'Prefrio/ServicioProcesoPrefrio.php',
            'Prefrio/ServicioCorreccionProcesoPrefrio.php',
            'Cargas/ServicioCarga.php',
            'Cargas/ServicioDespachoFrigorifico.php',
            'InspeccionSag/ServicioInspeccionSag.php',
        ];

        foreach ($servicios as $archivo) {
            $contenido = file_get_contents(app_path('Services/'.$archivo));

            $this->assertIsString($contenido);
            $this->assertStringContainsString(
                MotorTransicionesOperacionales::class,
                $contenido,
                "{$archivo} debe ejecutar sus cambios mediante el motor central.",
            );
            $this->assertStringContainsString(
                'motorTransiciones',
                $contenido,
                "{$archivo} debe conservar una dependencia explícita del motor.",
            );
        }
    }

    public function test_los_dominios_migrados_no_escriben_folios_mediante_query_builder(): void
    {
        $directorios = [
            app_path('Services/Estiba'),
            app_path('Services/Validacion'),
            app_path('Services/Prefrio'),
            app_path('Services/Cargas'),
            app_path('Services/InspeccionSag'),
        ];

        foreach ($directorios as $directorio) {
            foreach (glob($directorio.'/*.php') ?: [] as $archivo) {
                $contenido = file_get_contents($archivo);

                $this->assertIsString($contenido);
                $this->assertDoesNotMatchRegularExpression(
                    "/DB::table\\(['\"]folios['\"]\\).*?(?:update|delete|insert)\\(/s",
                    $contenido,
                    basename($archivo).' no debe saltarse modelos, eventos ni auditoría al escribir folios.',
                );
            }
        }
    }

    public function test_el_observador_cubre_estado_ubicacion_reservas_y_eventos(): void
    {
        $proveedor = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($proveedor);
        foreach ([
            'Folio::class',
            'UbicacionActual::class',
            'ReservaCargaFolio::class',
            'ProcesoPrefrio::class',
            'Carga::class',
            'Repaletizaje::class',
            'LoteInspeccionSag::class',
        ] as $modelo) {
            $this->assertStringContainsString($modelo, $proveedor);
        }
    }

    public function test_los_flujos_migrados_no_eluden_eventos_en_cambios_criticos(): void
    {
        $servicioCarga = file_get_contents(app_path('Services/Cargas/ServicioCarga.php'));
        $servicioDespacho = file_get_contents(app_path('Services/Cargas/ServicioDespachoFrigorifico.php'));
        $servicioRepaletizaje = file_get_contents(
            app_path('Services/Validacion/ServicioRepaletizaje.php'),
        );

        $this->assertIsString($servicioCarga);
        $this->assertIsString($servicioDespacho);
        $this->assertIsString($servicioRepaletizaje);
        $this->assertStringNotContainsString(
            'reservaActiva()->delete()',
            $servicioCarga.$servicioDespacho,
            'Las reservas deben eliminarse como modelos para registrar su cambio.',
        );
        $this->assertDoesNotMatchRegularExpression(
            "/DB::table\\(['\"]ubicaciones_actuales['\"]\\).*?(?:update|delete|insert)\\(/s",
            $servicioRepaletizaje,
            'La restauración de ubicación debe disparar el observador operacional.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/AutorizacionSagFolio::query\(\)(?:(?!->get\(\)).)*?->update\(/s',
            $servicioRepaletizaje,
            'Las revocaciones SAG deben auditar cada autorización afectada.',
        );
    }
}
