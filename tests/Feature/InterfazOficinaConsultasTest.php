<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaConsultasTest extends TestCase
{
    public function test_expone_los_modulos_seleccionables_de_consultas(): void
    {
        $this->get('/oficina/consultas')
            ->assertOk()
            ->assertSee('Oficina de consultas')
            ->assertSee('data-active-domain="consultas"', false)
            ->assertSee('data-queries-section="busqueda"', false)
            ->assertSee('Buscar en Estiba WMS');

        $this->get('/oficina/consultas/sag')
            ->assertOk()
            ->assertSee('data-queries-section="sag"', false)
            ->assertSee('Consultar productor SAG')
            ->assertSee('pendiente de asociación a cliente');

        $this->get('/oficina/consultas/productores')
            ->assertOk()
            ->assertSee('data-queries-section="productores"', false)
            ->assertSee('Productores CSG verificados');
    }
}
