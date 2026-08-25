<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazLobbiesMacromodulosTest extends TestCase
{
    public function test_publica_un_resumen_para_cada_macromodulo_sin_reemplazar_materiales(): void
    {
        foreach ([
            '/oficina/materia-prima' => ['materia-prima', 'Resumen de Materia Prima'],
            '/oficina/frigorifico' => ['frigorifico', 'Resumen de Frigorífico'],
            '/oficina/administracion' => ['administracion', 'Resumen de Gerencia y Administración'],
            '/oficina/consultas' => ['consultas', 'Resumen de Consultas'],
        ] as $ruta => [$dominio, $titulo]) {
            $this->get($ruta)
                ->assertOk()
                ->assertSee($titulo)
                ->assertSee('data-lobby-domain="'.$dominio.'"', false)
                ->assertSee('data-active-office="resumen"', false)
                ->assertSee('MÓDULOS DISPONIBLES')
                ->assertSee('Procesos del macromódulo');
        }

        $this->get('/oficina/materiales')
            ->assertOk()
            ->assertSee('data-materials-section="resumen"', false)
            ->assertSee('Resumen operacional');
    }

    public function test_lobbies_conservan_los_destinos_operacionales_directos(): void
    {
        $this->get('/oficina/materia-prima')
            ->assertSee('href="/oficina/materia-prima/lotes"', false)
            ->assertSee('href="/oficina/materia-prima/hidrocooler"', false)
            ->assertSee('href="/oficina/materia-prima/fruta-a-proceso"', false);

        $this->get('/oficina/frigorifico')
            ->assertSee('href="/oficina/validacion"', false)
            ->assertSee('href="/oficina/prefrio"', false)
            ->assertSee('href="/oficina/frigorifico/camaras"', false)
            ->assertSee('href="/oficina/cargas"', false);

        $this->get('/oficina/administracion')
            ->assertSee('href="/oficina/gerencia"', false)
            ->assertSee('href="/oficina/accesos"', false)
            ->assertSee('href="/oficina/administracion/integridad-operacional"', false);

        $this->get('/oficina/consultas')
            ->assertSee('href="/oficina/consultas/busqueda"', false)
            ->assertSee('href="/oficina/consultas/sag"', false)
            ->assertSee('href="/oficina/consultas/productores"', false);
    }

    public function test_resumen_filtra_tarjetas_y_consulta_indicadores_segun_permisos(): void
    {
        $script = file_get_contents(resource_path('js/office-domain-lobby.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('cardIsAccessible', $script);
        $this->assertStringContainsString("'/api/materia-prima/resumen'", $script);
        $this->assertStringContainsString("'/api/prefrio/resumen'", $script);
        $this->assertStringContainsString("'/api/inspeccion-sag/resumen'", $script);
        $this->assertStringContainsString("'/api/gerencia/resumen'", $script);
        $this->assertStringContainsString("'/api/consultas/resumen'", $script);
        $this->assertStringContainsString('Promise.allSettled(requests)', $script);
    }
}
