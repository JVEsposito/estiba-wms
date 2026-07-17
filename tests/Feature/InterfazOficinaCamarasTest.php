<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaCamarasTest extends TestCase
{
    public function test_la_configuracion_de_camaras_incluye_la_administracion_de_andenes(): void
    {
        $this->get('/oficina/camaras')
            ->assertOk()
            ->assertSee('Configuración de infraestructura')
            ->assertSee('Andenes creados')
            ->assertSee('Crear andén')
            ->assertSee('Código externo')
            ->assertSee('configurationModuleTabs', false)
            ->assertSee('dockWorkspace', false)
            ->assertSee('officeDockList', false)
            ->assertSee('dockForm', false)
            ->assertSee('aria-live="assertive"', false);
    }
}
