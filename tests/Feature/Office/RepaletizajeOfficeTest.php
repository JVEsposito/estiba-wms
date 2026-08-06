<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class RepaletizajeOfficeTest extends TestCase
{
    public function test_la_oficina_de_repaletizajes_es_visible(): void
    {
        $this->get('/oficina/validacion/repaletizajes')
            ->assertOk()
            ->assertSee('Repaletizajes')
            ->assertSee('CONSOLIDACIÓN DE SALDOS');
    }
}
