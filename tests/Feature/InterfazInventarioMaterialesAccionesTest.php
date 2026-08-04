<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazInventarioMaterialesAccionesTest extends TestCase
{
    public function test_inventario_presenta_las_acciones_en_un_menu_desplegable(): void
    {
        $navigation = file_get_contents(resource_path('js/office-navigation.js'));
        $menu = file_get_contents(resource_path('js/office-material-inventory-actions.js'));
        $materials = file_get_contents(resource_path('js/office-materials.js'));

        $this->assertIsString($navigation);
        $this->assertStringContainsString(
            "import './office-material-inventory-actions.js';",
            $navigation,
        );

        $this->assertIsString($menu);
        $this->assertStringContainsString("'#materialsInventoryBody'", $menu);
        $this->assertStringContainsString('material-inventory-action-toggle', $menu);
        $this->assertStringContainsString('<span>Acciones</span>', $menu);
        $this->assertStringContainsString("setAttribute('aria-haspopup', 'menu')", $menu);
        $this->assertStringContainsString('Despachar directo', $menu);
        $this->assertStringContainsString('Corregir código', $menu);
        $this->assertStringContainsString('Bloquear material', $menu);
        $this->assertStringContainsString('Liberar material', $menu);
        $this->assertStringContainsString('new MutationObserver(enhanceInventory)', $menu);
        $this->assertStringContainsString('sourceButton.click()', $menu);

        $this->assertIsString($materials);
        $this->assertStringContainsString('data-direct-dispatch', $materials);
        $this->assertStringContainsString('data-correct-material', $materials);
        $this->assertStringContainsString('data-block-material', $materials);
        $this->assertStringContainsString('data-release-material', $materials);
    }
}
