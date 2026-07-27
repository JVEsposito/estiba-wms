<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaMateriaPrimaTest extends TestCase
{
    public function test_expone_el_modulo_madre_y_sus_accesos_operacionales(): void
    {
        $this->get('/oficina/materia-prima')
            ->assertOk()
            ->assertSee('Materia prima')
            ->assertSee('Digitación de lotes')
            ->assertSee('/oficina/materia-prima/romana', escape: false)
            ->assertSee('/oficina/materia-prima/envases', escape: false)
            ->assertSee('Neto confirmado por digitador')
            ->assertSee('¿El lote necesita hidrocooler?');

        $this->get('/oficina/materia-prima/lotes')->assertOk();
        $this->get('/oficina/materia-prima/romana')
            ->assertRedirect('/oficina/romana');
        $this->get('/oficina/materia-prima/envases')
            ->assertRedirect('/oficina/envases/cuenta-corriente');
    }
}
