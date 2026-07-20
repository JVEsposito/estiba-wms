<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaCatalogoValidacionTest extends TestCase
{
    public function test_publica_la_configuracion_jerarquica_de_validacion(): void
    {
        $this->get('/oficina/validacion/catalogo')
            ->assertOk()
            ->assertSee('Catálogo jerárquico')
            ->assertSee('Clientes')
            ->assertSee('Marcas')
            ->assertSee('Categorías')
            ->assertSee('Especies')
            ->assertSee('Variedades')
            ->assertSee('Calibres')
            ->assertSee('Envases')
            ->assertSee('CSG')
            ->assertSee('Registros activos generados')
            ->assertSeeInOrder([
                'id="speciesForm"',
                'maxlength="100"',
                'id="varietyForm"',
                'maxlength="100"',
            ], false);
        $this->get('/oficina/validacion/catalogo')
            ->assertSee('id="categoryForm"', false)
            ->assertSee('Guardar categoría');
    }

    public function test_validacion_enlaza_el_catalogo_para_administradores(): void
    {
        $this->get('/oficina/validacion')
            ->assertOk()
            ->assertSee('/oficina/validacion/catalogo', false)
            ->assertSee('Configurar catálogo');
    }
}
