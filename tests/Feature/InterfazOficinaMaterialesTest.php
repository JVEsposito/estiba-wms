<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaMaterialesTest extends TestCase
{
    public function test_materiales_solo_consume_la_temporada_transversal(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('TEMPORADA TRANSVERSAL')
            ->assertSee('La temporada se crea, edita y activa en la oficina Accesos.')
            ->assertDontSee('seasonMaterialForm', false)
            ->assertDontSee('Guardar temporada')
            ->assertDontSee('Nueva temporada');
    }

    public function test_materiales_separa_sus_procesos_en_modulos_seleccionables(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('data-materials-section="resumen"', false)
            ->assertSee('Catálogos')
            ->assertSee('Recepciones')
            ->assertSee('Etiquetas')
            ->assertSee('Inventario')
            ->assertSee('Despachos')
            ->assertSee('Recetas')
            ->assertSee('Órdenes')
            ->assertSee('Exportaciones');

        $routes = [
            '/oficina/materiales/catalogos' => 'catalogos',
            '/oficina/materiales/recepciones' => 'recepciones',
            '/oficina/materiales/recepcion' => 'recepcion',
            '/oficina/materiales/inventario' => 'inventario',
            '/oficina/materiales/despachos' => 'despachos',
            '/oficina/materiales/recetas' => 'recetas',
            '/oficina/materiales/ordenes' => 'ordenes',
        ];

        foreach ($routes as $route => $section) {
            $this->get($route)
                ->assertOk()
                ->assertSee("data-materials-section=\"{$section}\"", false)
                ->assertSee("data-active-office=\"{$section}\"", false);
        }

        $this->get('/oficina/materiales/transformacion')
            ->assertRedirect('/oficina/materiales/recetas');
    }

    public function test_cada_bloque_principal_de_materiales_pertenece_a_una_sola_vista(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('id="materialsModuleOverview" data-materials-view="resumen"', false)
            ->assertSee('id="materialsAdminCatalogs" data-materials-view="catalogos"', false)
            ->assertSee('id="materialReceptionsWorkspace" data-materials-view="recepciones"', false)
            ->assertSee('id="materialLabelWorkspace" data-materials-view="recepcion"', false)
            ->assertSee('id="materialDispatchWorkspace" data-materials-view="despachos"', false)
            ->assertSee('id="materialInventoryWorkspace" data-materials-view="inventario"', false);
    }

    public function test_recepciones_de_materiales_expone_administracion_y_carga_masiva_de_bultos(): void
    {
        $this->get('/oficina/materiales/recepciones')
            ->assertOk()
            ->assertSee('Recepciones y folios')
            ->assertSee('Motivo de la corrección administrativa')
            ->assertSee('Eliminar y liberar folios')
            ->assertSee('Unidades por bulto')
            ->assertSee('type="date"', false);

        $script = file_get_contents(resource_path('js/office-material-receptions.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('puede_administrar_recepciones_materiales', $script);
        $this->assertStringContainsString('/administrar', $script);
        $this->assertStringContainsString("method: 'DELETE'", $script);
        $this->assertStringContainsString('Math.ceil(accepted / packageSize)', $script);
    }

    public function test_materiales_solo_actualiza_datos_de_la_seccion_visible(): void
    {
        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('class="materials-metrics" data-materials-view="resumen"', false);

        $office = file_get_contents(resource_path('js/office-materials.js'));
        $mobilePolling = file_get_contents(base_path('mobile/src/config/polling.ts'));
        $operationalScreen = file_get_contents(base_path('mobile/src/screens/OperationalScreen.tsx'));
        $materialDispatchOperation = file_get_contents(
            base_path('mobile/src/components/MaterialDispatchOperation.tsx'),
        );

        $this->assertIsString($office);
        $this->assertStringContainsString(
            "new Set(['resumen', 'catalogos', 'inventario', 'despachos'])",
            $office,
        );
        $this->assertStringContainsString(
            "new Set(['resumen', 'inventario', 'despachos'])",
            $office,
        );
        $this->assertStringContainsString('operationalRefreshIntervalMs = 30000', $office);
        $this->assertStringContainsString('if (!mainDataSections.has(section)) return;', $office);
        $this->assertStringContainsString(
            "materialDispatchSummaryPath = '/api/materiales/despachos?vista=resumen'",
            $office,
        );
        $this->assertStringContainsString(
            "materialInventorySummaryPath = '/api/materiales/inventario?vista=resumen'",
            $office,
        );
        $this->assertStringContainsString('loadInventoryPage(state.inventoryCurrentPage + 1)', $office);

        $this->assertIsString($mobilePolling);
        $this->assertStringContainsString('OPERATIONAL_POLL_INTERVAL_MS = 30_000', $mobilePolling);
        $this->assertIsString($operationalScreen);
        $this->assertStringContainsString('AppState.currentState', $operationalScreen);
        $this->assertIsString($materialDispatchOperation);
        $this->assertStringContainsString(
            'api.listMaterialDispatchSummaries(auth.token, ALL_STATES)',
            $materialDispatchOperation,
        );
        $this->assertStringContainsString(
            'api.getMaterialDispatch(auth.token, selectedSummary.id)',
            $materialDispatchOperation,
        );
    }
}
