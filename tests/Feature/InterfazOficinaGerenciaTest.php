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
            ->assertSee('Ocupación por cámara')
            ->assertSee('Disponibilidad de producto')
            ->assertSee('Materiales por ítem')
            ->assertSee('cameraOccupancyChart', false)
            ->assertSee('materialStockChart', false)
            ->assertSee('refreshDashboardButton', false)
            ->assertDontSee('<form id="create', false);
    }
}
