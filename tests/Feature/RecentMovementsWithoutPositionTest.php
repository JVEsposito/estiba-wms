<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecentMovementsWithoutPositionTest extends TestCase
{
    public function test_la_aplicacion_movil_tolera_movimientos_de_materiales_sin_posicion(): void
    {
        $component = file_get_contents(resource_path('../mobile/src/components/RecentMovements.tsx'));

        $this->assertIsString($component);
        $this->assertStringContainsString(
            "function movementEndLabel(end: Movement['origen'], fallback: string)",
            $component,
        );
        $this->assertStringContainsString(
            "end.posicion\n    ? end.posicion.etiqueta",
            $component,
        );
        $this->assertStringContainsString(
            ": 'Sin posición';",
            $component,
        );
        $this->assertStringContainsString(
            "movementEndLabel(movement.destino, 'Salida')",
            $component,
        );
    }
}
