<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EstibaVisualCatalogTest extends TestCase
{
    public function test_catalogo_exporta_componentes_reales_en_un_html_autocontenido(): void
    {
        $path = sys_get_temp_dir().'/estiba-catalog-'.uniqid().'.html';

        try {
            $this->artisan('ui:catalogo', ['--output' => $path])->assertSuccessful();
            $html = file_get_contents($path);
            $this->assertStringContainsString('Ejemplos ficticios', $html);
            $this->assertStringNotContainsString('@import', $html);
            $this->assertStringNotContainsString('<x-estiba', $html);

            $dom = new DOMDocument;
            $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//script[@src] | //link[@href] | //iframe')->length);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(1, $xpath->query('//li[@aria-current="step"]')->length);
            $this->assertSame(0, $xpath->query('//button[not(@type)]')->length);
            $this->assertSame(1, $xpath->query('//button[@aria-busy="true"][@disabled]')->length);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_componente_no_confunde_dato_desconocido_con_cero_y_acota_la_barra(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-estiba.metric label="Desconocido" />
            <x-estiba.metric label="Cero" :value="0" :total="20" />
            <x-estiba.metric label="Exceso" :value="25" :total="20" />
            BLADE);

        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $this->assertStringContainsString('Sin registro', $html);
        $this->assertSame(2, $xpath->query('//*[@role="progressbar"]')->length);
        $this->assertSame('0', $xpath->query('//*[@aria-label="Cero"]/@aria-valuenow')->item(0)->nodeValue);
        $this->assertSame('100', $xpath->query('//*[@aria-label="Exceso"]/@aria-valuenow')->item(0)->nodeValue);
        $this->assertSame('25 de 20', $xpath->query('//*[@aria-label="Exceso"]/@aria-valuetext')->item(0)->nodeValue);
    }

    public function test_encabezados_escapan_texto_y_botones_ocupados_no_envian_formularios(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-estiba.heading :title="$title" />
            <x-estiba.button :busy="true">Guardando</x-estiba.button>
            BLADE, ['title' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
    }
}
