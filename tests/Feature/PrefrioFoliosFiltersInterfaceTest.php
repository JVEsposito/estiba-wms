<?php

namespace TestsFeature;

use TestsTestCase;

class PrefrioFoliosFiltersInterfaceTest extends TestCase
{
    public function test_bandeja_de_prefrio_expone_los_siete_filtros_y_exige_los_dos_primeros(): void
    {
        $screen = file_get_contents(
            base_path('mobile/src/screens/PrefrioWorkspaceScreen.tsx'),
        );
        $domain = file_get_contents(base_path('mobile/src/domain/prefrio.ts'));
        $resource = file_get_contents(
            app_path('Http/Resources/FolioPrefrioResource.php'),
        );

        $this->assertIsString($screen);
        $this->assertIsString($domain);
        $this->assertIsString($resource);

        foreach ([
            '1. Cliente / exportadora *',
            '2. Especie *',
            '3. Variedad',
            '4. Condición SAG',
            '5. CSG',
            '6. Fecha de ingreso',
            '7. Condición térmica',
        ] as $filter) {
            $this->assertStringContainsString($filter, $screen);
        }

        $this->assertStringContainsString('hasRequiredFilters', $screen);
        $this->assertStringContainsString('tiene_condicion_sag', $screen);
        $this->assertStringContainsString('tiene_condicion_sag', $domain);
        $this->assertStringContainsString('tiene_condicion_sag', $resource);
        $this->assertStringContainsString('opcionales y combinables', $screen);
        $this->assertStringNotContainsString('setSearch', $screen);
    }
}
