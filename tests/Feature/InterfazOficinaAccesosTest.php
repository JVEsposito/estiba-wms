<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaAccesosTest extends TestCase
{
    public function test_la_oficina_de_accesos_se_encuentra_disponible(): void
    {
        $this->get('/oficina/accesos')
            ->assertOk()
            ->assertSee('Administración de accesos')
            ->assertSee('Usuarios y tablets autorizadas')
            ->assertSee('Mínimo 10 caracteres; debe contener al menos una letra y un número.')
            ->assertSee('createUserForm', false)
            ->assertSee('createDeviceForm', false)
            ->assertSee('/oficina/camaras', false)
            ->assertSee('/oficina/cargas', false)
            ->assertSee('aria-live="assertive"', false);
    }
}
