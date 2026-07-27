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

    public function test_prefrio_aparece_solamente_en_las_oficinas_del_dominio_frigorifico(): void
    {
        foreach ([
            '/oficina/frigorifico/camaras',
            '/oficina/cargas',
            '/oficina/validacion',
        ] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertSee('/oficina/prefrio', false);
        }

        foreach ([
            '/oficina/materiales',
            '/oficina/accesos',
            '/oficina/materia-prima',
        ] as $ruta) {
            $this->get($ruta)
                ->assertOk()
                ->assertDontSee('data-office-key="prefrio"', false);
        }
    }

    public function test_la_ruta_historica_de_camaras_deriva_al_dominio_frigorifico(): void
    {
        $this->get('/oficina/camaras')
            ->assertRedirect('/oficina/frigorifico/camaras');
    }
}
