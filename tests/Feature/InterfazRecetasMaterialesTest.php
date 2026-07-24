<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazRecetasMaterialesTest extends TestCase
{
    public function test_oficina_materiales_carga_la_integracion_de_recetas(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('office-material-recipes', false);

        $script = file_get_contents(resource_path('js/office-material-recipes.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('/api/materiales/transformaciones/recetas?per_page=100', $script);
        $this->assertStringContainsString('/versiones', $script);
        $this->assertStringContainsString('puede_consultar_transformaciones_materiales', $script);
        $this->assertStringContainsString('puede_administrar_recetas_materiales', $script);
        $this->assertStringContainsString("['insumo', 'material_mp']", $script);
        $this->assertStringContainsString("item.categoria_operacional === 'material_pt'", $script);
        $this->assertStringContainsString('Selecciona exactamente un componente principal.', $script);
    }
}
