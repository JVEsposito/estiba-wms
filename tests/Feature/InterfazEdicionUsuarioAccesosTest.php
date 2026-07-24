<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazEdicionUsuarioAccesosTest extends TestCase
{
    public function test_accesos_carga_la_gestion_de_edicion_de_usuarios(): void
    {
        $this->get('/oficina/accesos')
            ->assertOk()
            ->assertSee('office-user-management', false)
            ->assertSee('Al editar, déjala vacía para conservar la contraseña actual.')
            ->assertSee('id="createUserForm"', false)
            ->assertSee('id="usersTableBody"', false);
    }
}
