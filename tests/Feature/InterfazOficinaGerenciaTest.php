<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaGerenciaTest extends TestCase
{
    public function test_el_panel_gerencial_presenta_indicadores_y_graficos_de_solo_observacion(): void
    {
        $this->get('/oficina/gerencia')
            ->assertOk()
            ->assertSee('Panel gerencial')
            ->assertSee('Solo observación')
            ->assertSee('CAPACIDAD DE CÁMARAS')
            ->assertSee('INVENTARIO DE MATERIALES')
            ->assertSee('RECEPCIÓN ROMANA')
            ->assertSee('Ocupación por cámara')
            ->assertSee('Disponibilidad de producto')
            ->assertSee('Materiales por ítem')
            ->assertSee('Peso neto últimos 7 días')
            ->assertSee('cameraOccupancyChart', false)
            ->assertSee('materialStockChart', false)
            ->assertSee('weighbridgeReceptionChart', false)
            ->assertSee('refreshDashboardButton', false)
            ->assertDontSee('<form id="create', false);
    }
}
