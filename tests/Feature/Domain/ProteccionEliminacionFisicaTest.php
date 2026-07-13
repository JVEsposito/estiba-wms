<?php

namespace Tests\Feature\Domain;

use App\Models\Camara;
use App\Models\CondicionSag;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProteccionEliminacionFisicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_entidades_operacionales_se_desactivan_y_no_se_eliminan(): void
    {
        $usuario = User::factory()->create();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet 01',
        ]);
        $camara = Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara 01']);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'fila' => 'A',
            'profundidad' => 1,
            'nivel' => 1,
        ]);
        $condicion = CondicionSag::create([
            'codigo' => 'SAG-01',
            'nombre' => 'Condición 01',
        ]);
        $folio = Folio::create([
            'numero_folio' => 'FOLIO-001',
            'tipo_bulto' => 'pallet',
            'fecha_ingreso' => now(),
        ]);

        foreach ([$usuario, $dispositivo, $camara, $posicion, $condicion, $folio] as $modelo) {
            $this->assertEliminacionImpedida($modelo);
        }
    }

    private function assertEliminacionImpedida(Model $modelo): void
    {
        try {
            $modelo->delete();
            $this->fail(sprintf('%s permitió eliminación física.', $modelo::class));
        } catch (DomainException) {
            $this->assertDatabaseHas($modelo->getTable(), [
                $modelo->getKeyName() => $modelo->getKey(),
            ]);
        }
    }
}
