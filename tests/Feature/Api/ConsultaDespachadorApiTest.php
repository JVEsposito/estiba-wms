<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsultaDespachadorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_despachador_consulta_camaras_sin_administrarlas(): void
    {
        $despachador = $this->despachador();

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/camaras')
            ->assertOk();

        $this->actingAs($despachador, 'sanctum')
            ->postJson('/api/configuracion/camaras', [])
            ->assertForbidden();
    }

    public function test_consulta_solo_folios_disponibles_ubicados_y_sin_carga(): void
    {
        $despachador = $this->despachador();
        $folio = $this->crearFolioUbicado('FOLIO-DISPONIBLE-GUI');

        Folio::create([
            'numero_folio' => 'FOLIO-SIN-UBICACION-GUI',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/cargas/folios-disponibles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.numero_folio', $folio->numero_folio)
            ->assertJsonPath('data.0.ubicacion.camara.codigo', 'CAM-01')
            ->assertJsonPath('data.0.ubicacion.posicion.etiqueta', 'B01-P01-N1');

        $cargaId = $this->actingAs($despachador, 'sanctum')
            ->postJson('/api/cargas', [])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$cargaId}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk();

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/cargas/folios-disponibles')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function despachador(): User
    {
        return User::factory()->create([
            'rol' => RolUsuario::Despachador,
            'activo' => true,
        ]);
    }

    private function crearFolioUbicado(string $numeroFolio): Folio
    {
        $operador = User::factory()->create([
            'rol' => RolUsuario::Operador,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet de prueba',
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-01',
            'nombre' => 'Cámara 01',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $sesion = app(ServicioSesionEstiba::class)
            ->abrir($camara, $operador, $dispositivo);
        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: $numeroFolio,
            tipoBulto: TipoBulto::Pallet,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
        );

        return $movimiento->folio;
    }
}
