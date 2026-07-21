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
            ->assertSee('Configuración de solo lectura')
            ->assertDontSee('Guardar temporada')
            ->assertDontSee('Nueva temporada')
            ->assertSee('Combinaciones artículo–origen habilitadas')
            ->assertSee('validationHistoryBody', false)
            ->assertSee('importPreview', false)
            ->assertSee('/oficina/camaras', false)
            ->assertSee('/oficina/cargas', false)
            ->assertSee('aria-live="assertive"', false);
    }

    public function test_el_catalogo_de_validacion_no_crea_temporadas(): void
    {
        $this->get('/oficina/validacion/catalogo')
            ->assertOk()
            ->assertSee('Catálogo de la temporada seleccionada')
            ->assertSee('Las temporadas se crean y activan en la oficina Accesos.')
            ->assertDontSee('catalogSeasonForm', false)
            ->assertDontSee('Crear temporada');
    }
}
