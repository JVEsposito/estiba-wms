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
            ->assertSee('Registro de anulaciones');
    }
}
