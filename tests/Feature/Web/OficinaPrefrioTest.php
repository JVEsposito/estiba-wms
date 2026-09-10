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
            ->assertSee('PENDIENTES DE VERIFICACIÓN')
            ->assertSee('id="prefrioViewStatus"', false)
            ->assertSee('id="prefrioUpdatedAt"', false)
            ->assertSee('data-tone="warning"', false)
            ->assertSee('data-estiba-contrast="navy"', false)
            ->assertSee('<caption class="office-visually-hidden">', false)
            ->assertSee('id="operationalActionForm"', false)
            ->assertSee('id="decisionPanel"', false)
            ->assertSee('id="correctionDialog"', false);
    }

    public function test_la_interfaz_de_prefrio_conserva_contratos_y_paleta_operacional(): void
    {
        $javascript = file_get_contents(resource_path('js/office-prefrio.js'));
        $css = file_get_contents(resource_path('css/office-prefrio.css'));

        $this->assertStringContainsString("api('/api/prefrio/tuneles')", $javascript);
        $this->assertStringContainsString("api('/api/prefrio/resumen')", $javascript);
        $this->assertStringContainsString('/confirmar-armado', $javascript);
        $this->assertStringContainsString('/verificar', $javascript);
        $this->assertStringContainsString('/aprobar', $javascript);
        $this->assertStringContainsString('/reprocesar', $javascript);
        $this->assertStringContainsString('/cancelar', $javascript);
        $this->assertStringContainsString("['Enter', ' ']", $javascript);
        $this->assertStringContainsString('loadSummary()', $javascript);

        $this->assertStringContainsString('background: var(--corporate-canvas)', $css);
        $this->assertStringContainsString('background: #17394b', $css);
        $this->assertStringContainsString('.prefrio-heading__status', $css);
        $this->assertStringContainsString('color: #ffffff !important', $css);
        $this->assertStringNotContainsString('linear-gradient', $css);
        $this->assertStringNotContainsString('radial-gradient', $css);
    }

    public function test_prefrio_aparece_solamente_en_las_oficinas_del_dominio_frigorifico(): void
    {
        foreach ([
            '/oficina/frigorifico/camaras',
            '/oficina/frigorifico/despacho/cargas',
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
