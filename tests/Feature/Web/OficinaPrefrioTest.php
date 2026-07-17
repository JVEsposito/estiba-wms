<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class OficinaPrefrioTest extends TestCase
{
    public function test_publica_la_oficina_de_prefrio(): void
    {
        $this->get('/oficina/prefrio')
            ->assertOk()
            ->assertSee('Tablero de Prefrío')
            ->assertSee('Túneles configurables')
            ->assertSee('Procesos históricos')
            ->assertSee('Nuevo proceso')
            ->assertSee('Nuevo túnel')
            ->assertSee('PENDIENTES DE VERIFICACIÓN');
    }
}
