<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class CamarasOperacionalesOfficeTest extends TestCase
{
    public function test_publica_la_vista_operacional_pt_sin_reemplazar_la_configuracion(): void
    {
        $this->get('/oficina/frigorifico/camaras')
            ->assertOk()
            ->assertSee('Cámaras PT')
            ->assertSee('Plano operacional por bandas')
            ->assertSee('Control ambiental')
            ->assertSee('Bandas operacionales')
            ->assertSee('Maniobras activas')
            ->assertSee('Eventos recientes')
            ->assertSee('id="operationalCameraList"', false)
            ->assertSee('id="cameraBandMap"', false)
            ->assertSee('data-camera-mode="operacion"', false)
            ->assertSee('data-estiba-contrast="navy"', false);

        $this->get('/oficina/administracion/camaras')
            ->assertOk()
            ->assertSee('Crear cámara')
            ->assertSee('id="createCameraForm"', false)
            ->assertSee('id="dockForm"', false)
            ->assertSee('data-camera-mode="configuracion"', false);
    }

    public function test_cliente_operacional_usa_solo_contratos_de_consulta_existentes(): void
    {
        $script = file_get_contents(resource_path('js/office-cameras.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("api('/api/camaras')", $script);
        $this->assertStringContainsString('/api/camaras/${id}/plano', $script);
        $this->assertStringContainsString('/api/movimientos/recientes?camara_id=', $script);
        $this->assertStringContainsString('/api/control-ambiental/estado?camara_id=', $script);
        $this->assertStringContainsString('bandas_operacionales', $script);
        $this->assertStringContainsString('reserva_operacional', $script);
        $this->assertStringContainsString('SIN REGISTRO', $script);
    }

    public function test_estilos_respetan_temas_y_contraste_en_superficies_navy(): void
    {
        $cameraStyles = file_get_contents(resource_path('css/office-cameras.css'));
        $corporateStyles = file_get_contents(resource_path('css/office-corporate.css'));
        $operationView = file_get_contents(resource_path('views/office/operation-now.blade.php'));

        $this->assertIsString($cameraStyles);
        $this->assertIsString($corporateStyles);
        $this->assertStringContainsString('var(--corporate-canvas)', $cameraStyles);
        $this->assertStringContainsString('var(--corporate-card)', $cameraStyles);
        $this->assertStringContainsString('var(--success-bg)', $cameraStyles);
        $this->assertStringContainsString('[data-estiba-contrast="navy"]', $corporateStyles);
        $this->assertStringContainsString('.operation-now-facility__cell[data-tone="success"]', $corporateStyles);
        $this->assertStringContainsString('data-estiba-contrast="navy"', $operationView);
        $this->assertStringNotContainsString('linear-gradient', $cameraStyles);
        $this->assertStringNotContainsString('radial-gradient', $cameraStyles);
    }
}
