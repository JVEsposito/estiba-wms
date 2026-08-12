<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazCalendarioEmbarquesTest extends TestCase
{
    public function test_calendario_se_encuentra_en_frigorifico_y_expone_el_flujo_completo(): void
    {
        $this->get('/oficina/frigorifico/calendario-embarques')
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

    public function test_navegacion_pt_muestra_calendario_antes_de_cargas(): void
    {
        $response = $this->get('/oficina/cargas')->assertOk();
        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, 'data-office-key="cargas"'),
            strpos($content, 'data-office-key="embarques"'),
        );
    }
}
