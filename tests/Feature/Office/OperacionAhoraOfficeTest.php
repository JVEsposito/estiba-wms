<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class OperacionAhoraOfficeTest extends TestCase
{
    public function test_publica_el_centro_de_control_operacional_sin_acciones_de_negocio(): void
    {
        $this->get('/oficina/operacion-ahora')
            ->assertOk()
            ->assertSee('Operación ahora')
            ->assertSee('CENTRO DE CONTROL · INFORMACIÓN EN VIVO')
            ->assertSee('Vista operacional de recintos')
            ->assertSee('Camareros activos')
            ->assertSee('Prefrío')
            ->assertSee('Estado, progreso y producto')
            ->assertSee('<th scope="col">Estado</th>', false)
            ->assertSee('<th scope="col">Progreso</th>', false)
            ->assertSee('<th scope="col">Producto</th>', false)
            ->assertSee('Incidencias abiertas')
            ->assertSee('Alertas operacionales')
            ->assertSee('ACCESOS RÁPIDOS')
            ->assertSee('id="operationCameraRows"', false)
            ->assertSee('id="operationOperatorList"', false)
            ->assertSee('id="operationTunnelList"', false)
            ->assertSee('id="operationIncidentRows"', false)
            ->assertSee('id="operationFacilityMap"', false)
            ->assertSee('id="operationAlertRows"', false)
            ->assertSee('class="operation-now-syncbar"', false)
            ->assertSee('class="operation-now-syncbar__metrics"', false)
            ->assertSee('data-active-office="operacion-ahora"', false)
            ->assertDontSee('class="operation-now-metrics"', false)
            ->assertDontSee('<form', false);
    }

    public function test_navegacion_y_resumen_exponen_operacion_ahora_con_permiso_gerencial(): void
    {
        $this->get('/oficina/operacion-ahora')
            ->assertOk()
            ->assertSee('data-navigation-module="gerencia.panel"', false)
            ->assertSee('data-navigation-permissions="puede_consultar_panel_gerencial"', false);

        $this->get('/oficina/administracion')
            ->assertOk()
            ->assertSee('href="/oficina/operacion-ahora"', false)
            ->assertSee('TIEMPO REAL');
    }

    public function test_cliente_consume_el_contrato_actual_y_coordina_refrescos(): void
    {
        $script = file_get_contents(resource_path('js/office-operation-now.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("api('/api/operacion-ahora')", $script);
        $this->assertStringContainsString('createOperationalPoller', $script);
        $this->assertStringContainsString('actualizacion_sugerida_segundos', $script);
        $this->assertStringContainsString('validateSnapshot', $script);
        $this->assertStringContainsString('SIN REGISTRO', $script);
        $this->assertStringContainsString('renderFacility', $script);
        $this->assertStringContainsString('buildOperationalAlerts', $script);
        $this->assertStringContainsString('proceso_activo', $script);
        $this->assertStringContainsString('process?.productos', $script);
        $this->assertStringContainsString('tunnelProgress', $script);
        $this->assertStringContainsString('pauseWhenHidden', file_get_contents(resource_path('js/shared/operational-poller.js')));
        $this->assertStringNotContainsString("method: 'POST'", $script);
        $this->assertStringNotContainsString("method: 'PUT'", $script);
    }

    public function test_conserva_densidad_operacional_y_respuesta_tactil_sin_decoracion_generica(): void
    {
        $styles = file_get_contents(resource_path('css/office-operation-now.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('overflow-x: auto', $styles);
        $this->assertStringContainsString('@media (pointer: coarse)', $styles);
        $this->assertStringContainsString('--eui-density-touch-control', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
        $this->assertStringContainsString('background: #17394b', $styles);
        $this->assertStringContainsString('operation-now-facility__grid', $styles);
        $this->assertStringContainsString('grid-template-columns: repeat(12, minmax(0, 1fr))', $styles);
        $this->assertStringContainsString('max-height: 196px', $styles);
        $this->assertStringNotContainsString('linear-gradient', $styles);
        $this->assertStringNotContainsString('radial-gradient', $styles);
    }

    public function test_shell_conserva_funciones_y_usa_una_apariencia_operacional(): void
    {
        $view = file_get_contents(resource_path('views/components/office/navigation.blade.php'));
        $styles = file_get_contents(resource_path('css/office-shell.css'));

        $this->assertIsString($view);
        $this->assertIsString($styles);
        $this->assertStringContainsString('SISTEMA DE GESTIÓN', $view);
        $this->assertStringContainsString('estiba-office-system', $view);
        $this->assertStringContainsString('aria-label="Operación en tiempo real"', $view);
        $this->assertStringContainsString('class="estiba-office-status"', $view);
        $this->assertStringContainsString('data-office-context-status', $view);
        $this->assertStringContainsString('data-office-context-refresh', $view);
        $this->assertStringContainsString('FRÍO QUE', $view);
        $this->assertStringContainsString('background: #106fc0', $styles);
        $this->assertStringNotContainsString('officeThemeSelector', $view);
    }
}
