<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class AnulacionValidacionPalletOfficeTest extends TestCase
{
    public function test_oficina_de_anulaciones_esta_disponible(): void
    {
        $this->get('/oficina/validacion/anulaciones')
            ->assertOk()
            ->assertSee('Anulaciones de pallets')
            ->assertSee('Pallets aún anulables')
            ->assertSee('Registro de anulaciones')
            ->assertSee('annulmentCorrectionDialog', false)
            ->assertSee('Cantidad de cajas');

        $script = file_get_contents(resource_path('js/office-validation-annulments.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('Corregir datos', $script);
        $this->assertStringContainsString('/corregir', $script);
        $this->assertStringContainsString('puede_corregir_validaciones_pallet', $script);
    }
}
