<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaConsultasTest extends TestCase
{
    public function test_expone_la_oficina_de_consultas_y_el_flujo_sag(): void
    {
        $this->get('/oficina/consultas')
            ->assertOk()
            ->assertSee('Oficina de consultas')
            ->assertSee('data-active-domain="consultas"', false)
            ->assertSee('Trazabilidad operacional')
            ->assertSee('Buscar en Estiba WMS')
            ->assertSee('Consultar productor SAG')
            ->assertSee('pendiente de asociación a cliente')
            ->assertSee('Productores CSG verificados');
    }
}
