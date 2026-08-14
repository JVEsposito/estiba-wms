<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaConsultasTest extends TestCase
{
    public function test_expone_los_modulos_seleccionables_de_consultas(): void
    {
        $this->get('/oficina/consultas')
            ->assertOk()
            ->assertSee('Oficina de consultas')
            ->assertSee('data-active-domain="consultas"', false)
            ->assertSee('data-queries-section="busqueda"', false)
            ->assertSee('Buscar en Estiba WMS');

        $this->get('/oficina/consultas/sag')
            ->assertOk()
            ->assertSee('data-queries-section="sag"', false)
            ->assertSee('Consultar productor SAG')
            ->assertSee('pendiente de asociación a cliente');

        $this->get('/oficina/consultas/productores')
            ->assertOk()
            ->assertSee('data-queries-section="productores"', false)
            ->assertSee('Productores CSG verificados');
    }

    public function test_consulta_y_asociacion_actualizan_la_vista_sin_bloquear_una_recarga_completa(): void
    {
        $script = file_get_contents(resource_path('js/office-queries.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('payload.data.forEach(upsertProducer);', $script);
        $this->assertStringContainsString('upsertProducer(payload.data);', $script);
        $this->assertStringContainsString('void refreshSummaryQuietly();', $script);
        $this->assertStringContainsString("state.activeSection === 'productores'", $script);
        $this->assertStringContainsString('includeProducers: false', $script);
        $this->assertStringNotContainsString(
            "        await loadBase();\n        toast(payload.message);",
            $script,
        );
    }

    public function test_expediente_distingue_producto_de_material_y_expone_su_existencia(): void
    {
        $script = file_get_contents(resource_path('js/office-queries.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("folio.tipo_bulto === 'material'", $script);
        $this->assertStringContainsString('Identidad del material', $script);
        $this->assertStringContainsString('Saldo por almacén y centro de costo', $script);
        $this->assertStringContainsString('recepciones_material', $script);
        $this->assertStringContainsString('consumos_material', $script);
        $this->assertStringContainsString('formatMaterialQuantity', $script);
    }
}
