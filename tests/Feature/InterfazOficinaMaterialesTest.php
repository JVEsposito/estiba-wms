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
            ->assertSee('Etiquetas')
            ->assertSee('Inventario')
            ->assertSee('Despachos')
            ->assertSee('Recetas')
            ->assertSee('Órdenes')
            ->assertSee('Exportaciones');

        $routes = [
            '/oficina/materiales/catalogos' => 'catalogos',
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
            ->assertSee('id="materialLabelWorkspace" data-materials-view="recepcion"', false)
            ->assertSee('id="materialDispatchWorkspace" data-materials-view="despachos"', false)
            ->assertSee('id="materialInventoryWorkspace" data-materials-view="inventario"', false);
    }
}
