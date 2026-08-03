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
            ->assertSee('Fruta a proceso')
            ->assertSee('/oficina/materia-prima/romana', escape: false)
            ->assertSee('/oficina/materia-prima/fruta-a-proceso', escape: false)
            ->assertSee('/oficina/materia-prima/envases', escape: false)
            ->assertSee('Neto confirmado por digitador')
            ->assertSee('¿El lote necesita hidrocooler?');

        $this->get('/oficina/materia-prima/lotes')->assertOk();
        $this->get('/oficina/materia-prima/fruta-a-proceso')
            ->assertOk()
            ->assertSee('Fruta a proceso')
            ->assertSee('Confirmar viaje')
            ->assertSee('Retornos de Packing')
            ->assertSee('Crear sublotes')
            ->assertSee('PENDIENTES DE UBICACIÓN')
            ->assertSee('Cantidad de bins')
            ->assertSee('Línea de proceso')
            ->assertSee('N° de orden');
        $this->get('/oficina/materia-prima/romana')
            ->assertRedirect('/oficina/romana');
        $this->get('/oficina/materia-prima/envases')
            ->assertRedirect('/oficina/envases/cuenta-corriente');
    }

    public function test_revalida_catalogos_sin_descargarlos_en_cada_refresco(): void
    {
        $interfaz = file_get_contents(base_path('resources/js/office-raw-material.js'));

        $this->assertStringContainsString(
            "headers.set('If-None-Match', state.catalogEtag)",
            $interfaz,
        );
        $this->assertStringContainsString(
            'response.status === 304 && state.catalogs',
            $interfaz,
        );
        $this->assertStringContainsString(
            "state.catalogEtag = response.headers.get('ETag')",
            $interfaz,
        );
        $this->assertStringContainsString(
            'loadCatalogs(),',
            $interfaz,
        );
    }
}
