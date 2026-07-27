<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavegacionOficinasPorDominioTest extends TestCase
{
    public function test_materia_prima_muestra_solo_sus_oficinas_secundarias(): void
    {
        $this->get('/oficina/materia-prima')
            ->assertOk()
            ->assertSee('data-active-domain="materia-prima"', false)
            ->assertSee('Romana')
            ->assertSee('Digitación de Lotes')
            ->assertSee('Cuenta Envases')
            ->assertDontSee('Cargas &amp; Despachos', false);
    }

    public function test_frigorifico_muestra_sus_oficinas_y_no_la_configuracion_administrativa(): void
    {
        $this->get('/oficina/frigorifico/camaras')
            ->assertOk()
            ->assertSee('data-active-domain="frigorifico"', false)
            ->assertSee('Validación')
            ->assertSee('Prefrío')
            ->assertSee('Cargas &amp; Despachos', false)
            ->assertDontSee('Configuración de cámaras');
    }

    public function test_configuracion_de_camaras_pertenece_a_administracion(): void
    {
        $this->get('/oficina/administracion/camaras')
            ->assertOk()
            ->assertSee('data-active-domain="administracion"', false)
            ->assertSee('Configuración de cámaras')
            ->assertSee('data-camera-mode="configuracion"', false);

        $this->get('/oficina/camaras')
            ->assertRedirect('/oficina/frigorifico/camaras');
    }
}
