<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazCalendarioEmbarquesTest extends TestCase
{
    public function test_calendario_se_encuentra_en_frigorifico_y_expone_el_flujo_completo(): void
    {
        $this->get('/oficina/frigorifico/despacho/calendario')
            ->assertOk()
            ->assertSee('Calendario de embarques')
            ->assertSee('PLANIFICACIÓN 24/7')
            ->assertSee('Instructivos del embarque')
            ->assertSee('País destino')
            ->assertSee('Puerto / aeropuerto / paso destino')
            ->assertSee('Autorizar sobrecupo')
            ->assertSee('Confirmar y crear orden CAR')
            ->assertSee('data-active-office="embarques"', false);
    }

    public function test_navegacion_pt_agrupa_cargas_estatus_y_calendario_en_ese_orden(): void
    {
        $response = $this->get('/oficina/frigorifico/despacho/cargas')->assertOk();
        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, 'data-office-key="despacho-estatus"'),
            strpos($content, 'data-office-key="cargas"'),
        );
        $this->assertLessThan(
            strpos($content, 'data-office-key="embarques"'),
            strpos($content, 'data-office-key="despacho-estatus"'),
        );
        $response->assertSee('class="estiba-office-subgroup">Despacho</p>', false);
    }

    public function test_la_ruta_historica_del_calendario_conserva_compatibilidad(): void
    {
        $this->get('/oficina/frigorifico/calendario-embarques')
            ->assertRedirect('/oficina/frigorifico/despacho/calendario')
            ->assertStatus(301);
    }
}
