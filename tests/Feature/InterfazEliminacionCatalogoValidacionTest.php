<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazEliminacionCatalogoValidacionTest extends TestCase
{
    public function test_catalogo_publica_controles_para_eliminar_y_revisar_elementos_retirados(): void
    {
        $this->get('/oficina/validacion/catalogo')
            ->assertOk()
            ->assertSee('id="catalogToggleInactive"', false)
            ->assertSee('Mostrar eliminados')
            ->assertSee('se conserva su trazabilidad histórica');

        $javascript = file_get_contents(resource_path('js/office-validation-catalog.js'));

        $this->assertIsString($javascript);
        $this->assertStringContainsString('data-delete-type', $javascript);
        $this->assertStringContainsString("method: 'DELETE'", $javascript);
        $this->assertStringContainsString('¿Eliminar', $javascript);
    }
}
