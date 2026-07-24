<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazCategoriasProveedorMaterialTest extends TestCase
{
    public function test_oficina_y_tablet_publican_la_seleccion_de_categorias_por_proveedor(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('providerCategoryOptions', false)
            ->assertSee('Categorías habilitadas', false);

        $office = file_get_contents(resource_path('js/office-materials.js'));
        $mobile = file_get_contents(base_path('mobile/src/screens/MaterialReceptionScreen.tsx'));

        $this->assertIsString($office);
        $this->assertStringContainsString('data-client-id', $office);
        $this->assertStringContainsString('data-category', $office);
        $this->assertStringContainsString('Proveedor, clientes y categorías actualizados.', $office);

        $this->assertIsString($mobile);
        $this->assertStringContainsString('enabledCategories', $mobile);
        $this->assertStringContainsString('changeSupplier', $mobile);
        $this->assertStringContainsString("disabled={!form.proveedor_material_id}", $mobile);
    }
}
