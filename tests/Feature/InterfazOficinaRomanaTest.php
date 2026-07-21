<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaRomanaTest extends TestCase
{
    public function test_la_oficina_presenta_el_flujo_completo_de_pesaje(): void
    {
        $this->get('/oficina/romana')
            ->assertOk()
            ->assertSee('Control de Romana')
            ->assertSee('Registrar ingreso')
            ->assertSee('PENDIENTES DE DESTARE')
            ->assertSee('Peso bruto')
            ->assertSee('Peso tara')
            ->assertSee('Aviso de Recibo PDF')
            ->assertSee('receptionForm', false)
            ->assertSee('tareForm', false)
            ->assertSee('/oficina/gerencia', false)
            ->assertSee('/oficina/prefrio', false);
    }
}
