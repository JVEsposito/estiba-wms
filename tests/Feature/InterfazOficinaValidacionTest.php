<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazOficinaValidacionTest extends TestCase
{
    public function test_la_oficina_de_validacion_se_encuentra_disponible(): void
    {
        $this->get('/oficina/validacion')
            ->assertOk()
            ->assertSee('Validación de pallets')
            ->assertSee('Importar planilla')
            ->assertSee('Combinaciones artículo–origen habilitadas')
            ->assertSee('validationHistoryBody', false)
            ->assertSee('importPreview', false)
            ->assertSee('/oficina/camaras', false)
            ->assertSee('/oficina/cargas', false)
            ->assertSee('aria-live="assertive"', false);
    }
}
