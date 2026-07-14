<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaCargasTest extends TestCase
{
    public function test_la_oficina_de_cargas_se_encuentra_disponible(): void
    {
        $this->get('/oficina/cargas')
            ->assertOk()
            ->assertSee('Órdenes de carga')
            ->assertSee('Ingresar a cargas')
            ->assertSee('Crear primera orden')
            ->assertSee('aria-labelledby="folioAddTitle"', false)
            ->assertSee('aria-live="assertive"', false);
    }

    public function test_la_oficina_de_camaras_enlaza_el_modulo_de_cargas(): void
    {
        $this->get('/oficina/camaras')
            ->assertOk()
            ->assertSee('/oficina/cargas', false)
            ->assertDontSee('Cargas · próximamente');
    }
}
