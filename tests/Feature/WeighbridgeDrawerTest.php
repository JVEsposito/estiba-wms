<?php

namespace Tests\Feature;

use Tests\TestCase;

class WeighbridgeDrawerTest extends TestCase
{
    public function test_romana_publica_el_expediente_lateral_sin_cambiar_sus_contratos_operativos(): void
    {
        $navigation = file_get_contents(resource_path('js/office-navigation.js'));
        $drawer = file_get_contents(resource_path('js/office-weighbridge-drawer.js'));
        $styles = file_get_contents(resource_path('css/office-weighbridge-drawer.css'));

        $this->assertIsString($navigation);
        $this->assertIsString($drawer);
        $this->assertIsString($styles);

        $this->assertStringContainsString(
            "import('./office-weighbridge-drawer.js')",
            $navigation,
        );
        $this->assertStringContainsString(
            "window.location.pathname.startsWith('/oficina/romana')",
            $navigation,
        );
        $this->assertStringContainsString(
            "Object.defineProperty(detail, 'scrollIntoView'",
            $drawer,
        );
        $this->assertStringContainsString(
            'data-drawer-tab="summary"',
            $drawer,
        );
        $this->assertStringContainsString(
            'data-drawer-tab="weighings"',
            $drawer,
        );
        $this->assertStringContainsString(
            'data-drawer-tab="events"',
            $drawer,
        );
        $this->assertStringContainsString(
            '.weighbridge-table tbody tr[data-reception-id].is-selected td',
            $styles,
        );
        $this->assertStringContainsString(
            'body.has-reception-drawer .weighbridge-workspace',
            $styles,
        );
        $this->assertStringContainsString(
            '@media (max-width: 900px)',
            $styles,
        );
    }
}
