<?php

namespace Tests\Feature;

use Tests\TestCase;

class InterfazImportacionRecepcionMaterialesTest extends TestCase
{
    public function test_la_previsualizacion_se_invalida_al_cambiar_la_planilla(): void
    {
        $script = file_get_contents(resource_path('js/office-material-reception-import.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('previewFingerprint', $script);
        $this->assertStringContainsString('requestSequence', $script);
        $this->assertStringContainsString(
            "elements.form.elements.archivo.addEventListener('change'",
            $script,
        );
        $this->assertStringContainsString(
            'La planilla seleccionada cambió; vuelve a previsualizarla antes de cargar.',
            $script,
        );
    }
}
