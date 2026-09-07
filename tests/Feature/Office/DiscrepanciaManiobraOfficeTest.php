<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class DiscrepanciaManiobraOfficeTest extends TestCase
{
    public function test_la_bandeja_de_discrepancias_esta_disponible_en_la_oficina_de_frigorifico(): void
    {
        $this->get('/oficina/frigorifico/discrepancias')
            ->assertOk()
            ->assertSee('Discrepancias de maniobras')
            ->assertSee('El estado físico manda')
            ->assertSee('data-active-office="discrepancias"', false)
            ->assertSee('id="discrepanciesList"', false);
    }

    public function test_la_interfaz_conserva_las_reglas_de_resolucion_del_backend(): void
    {
        $view = file_get_contents(resource_path('views/office/discrepancies.blade.php'));
        $script = file_get_contents(resource_path('js/office-discrepancies.js'));
        $styles = file_get_contents(resource_path('css/office-discrepancies.css'));

        $this->assertIsString($view);
        $this->assertIsString($script);
        $this->assertIsString($styles);
        $this->assertStringContainsString('resources/css/estiba-ui.css', $view);
        $this->assertStringContainsString('/api/discrepancias-maniobra?', $script);
        $this->assertStringContainsString('/resolver', $script);
        $this->assertStringContainsString('version_maniobra', $script);
        $this->assertStringContainsString('restricciones?.cancelar', $script);
        $this->assertStringContainsString('error.status === 409', $script);
        $this->assertStringContainsString('var(--eui-color-border)', $styles);
    }
}
