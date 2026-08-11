<?php

namespace Tests\Feature\Office;

use Tests\TestCase;

class NavegacionFrigorificoOfficeTest extends TestCase
{
    public function test_repaletizajes_y_anulaciones_forman_parte_de_la_subnavegacion_principal(): void
    {
        $response = $this->get('/oficina/validacion/repaletizajes')->assertOk();
        $html = $response->getContent();

        $validacion = strpos($html, 'data-office-key="validacion"');
        $repaletizajes = strpos($html, 'data-office-key="repaletizajes"');
        $anulaciones = strpos($html, 'data-office-key="anulaciones-validacion"');

        $this->assertNotFalse($validacion);
        $this->assertNotFalse($repaletizajes);
        $this->assertNotFalse($anulaciones);
        $this->assertStringNotContainsString('data-office-key="catalogo-validacion"', $html);
        $this->assertTrue($validacion < $repaletizajes);
        $this->assertTrue($repaletizajes < $anulaciones);
        $this->assertSame(1, substr_count($html, 'data-office-key="repaletizajes"'));
        $this->assertSame(1, substr_count($html, 'data-office-key="anulaciones-validacion"'));
        $this->assertMatchesRegularExpression(
            '/class="is-active"\s+data-office-key="repaletizajes"/',
            $html,
        );
    }
}
