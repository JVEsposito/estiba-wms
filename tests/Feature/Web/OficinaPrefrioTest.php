<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class OficinaPrefrioTest extends TestCase
{
    public function test_publica_la_oficina_de_prefrio(): void
    {
        $this->get('/oficina/prefrio')
            ->assertOk()
            ->assertSee('Tablero de Prefrío')
            ->assertSee('Túneles configurables')
            ->assertSee('Procesos históricos')
            ->assertSee('Nuevo proceso')
            ->assertSee('Nuevo túnel')
            ->assertSee('PENDIENTES DE VERIFICACIÓN');
    }

    public function test_prefrio_queda_disponible_desde_la_navegacion_de_oficina(): void
    {
        foreach ([
            '/oficina/camaras',
            '/oficina/cargas',
            '/oficina/materiales',
            '/oficina/validacion',
            '/oficina/accesos',
        ] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertSee('/oficina/prefrio', false);
        }
    }
}
