<?php

namespace Tests\Feature;

use Tests\TestCase;

class InspeccionSagInterfaceTest extends TestCase
{
    public function test_interfaz_sag_muestra_los_siete_filtros_y_nomenclatura_operacional(): void
    {
        $this->get('/oficina/frigorifico/inspeccion-sag')
            ->assertOk()
            ->assertSee('Inspección SAG')
            ->assertSee('1. Cliente / exportadora')
            ->assertSee('2. Especie')
            ->assertSee('3. Variedad')
            ->assertSee('4. Condición SAG')
            ->assertSee('5. CSG')
            ->assertSee('6. Fecha ingreso')
            ->assertSee('7. Condición térmica')
            ->assertSee('AO')
            ->assertSee('AU')
            ->assertSee('AF')
            ->assertSee('Cambio de mercado')
            ->assertSee('País individual o bloque completo');

        $navegacion = file_get_contents(resource_path('views/components/office/navigation.blade.php'));
        $this->assertIsString($navegacion);
        $this->assertStringContainsString('frigorifico.inspeccion-sag', $navegacion);
        $this->assertStringContainsString('/oficina/frigorifico/inspeccion-sag', $navegacion);
    }
}
