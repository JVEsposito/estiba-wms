<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaMaterialesTest extends TestCase
{
    public function test_materiales_solo_consume_la_temporada_transversal(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('TEMPORADA TRANSVERSAL')
            ->assertSee('La temporada se crea, edita y activa en la oficina Accesos.')
            ->assertDontSee('seasonMaterialForm', false)
            ->assertDontSee('Guardar temporada')
            ->assertDontSee('Nueva temporada');
    }
}
