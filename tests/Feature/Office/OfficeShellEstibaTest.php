<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class OfficeShellEstibaTest extends TestCase
{
    public function test_el_shell_conserva_dominios_oficinas_y_estado_activo(): void
    {
        $response = $this->get('/oficina/frigorifico/discrepancias')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('office-shell__topbar estiba-ui', $html);
        $this->assertStringContainsString('office-shell__subnavigation estiba-ui', $html);
        $this->assertSame(5, substr_count($html, 'data-domain-key='));
        $this->assertSame(1, substr_count($html, 'aria-current="location"'));
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString('data-active-domain="frigorifico"', $html);
        $this->assertStringContainsString('data-active-office="discrepancias"', $html);
        $this->assertStringContainsString('data-navigation-permissions="puede_supervisar"', $html);
    }

    public function test_el_shell_usa_las_fundaciones_visuales_sin_cambiar_los_temas_guardados(): void
    {
        $navigation = file_get_contents(resource_path('views/components/office/navigation.blade.php'));
        $styles = file_get_contents(resource_path('css/office-corporate.css'));
        $javascript = file_get_contents(resource_path('js/office-navigation.js'));

        $this->assertIsString($navigation);
        $this->assertIsString($styles);
        $this->assertIsString($javascript);
        $this->assertStringContainsString('resources/css/estiba-ui.css', $navigation);
        $this->assertStringContainsString('--header-bg: var(--eui-color-navy)', $styles);
        $this->assertStringContainsString('--subnav-bg: var(--eui-color-surface)', $styles);
        $this->assertStringContainsString('office-shell__topbar .office-domain-link[aria-current="location"]', $styles);
        $this->assertStringContainsString('<option value="dark-industrial">Industrial oscuro</option>', $javascript);
        $this->assertStringContainsString("const themeKey = 'estiba_wms_office_theme';", $javascript);
    }
}
