<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\Posicion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MovimientoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ubicar_crea_el_folio_y_repetir_la_operacion_es_idempotente(): void
    {
        [, , $token] = $this->crearIdentidad();
        [$camara, $posicion] = $this->crearCamara('CAM-01');
        $sesionId = $this->abrirSesion($token, $camara);
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'numero_folio' => 'FOLIO-NUEVO',
            'tipo_bulto' => 'pallet',
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesionId,
            'version_destino_conocida' => 0,
            'generado_dispositivo_at' => now()->toAtomString(),
            'datos_folio' => [
                'variedad' => 'Lapins',
                'calibre' => 'J',
                'exportadora' => 'Exportadora de prueba',
            ],
        ];

        $movimientoId = $this->withToken($token)
            ->postJson('/api/movimientos/ubicar', $payload)
            ->assertOk()
            ->assertJsonPath('data.operacion_id', $operacionId)
            ->assertJsonPath('data.tipo_movimiento', 'ubicacion_inicial')
            ->assertJsonPath('data.folio.numero_folio', 'FOLIO-NUEVO')
            ->assertJsonPath('data.folio.tipo_bulto', 'pallet')
            ->assertJsonPath('data.origen', null)
            ->assertJsonPath('data.destino.camara.id', $camara->id)
            ->assertJsonPath('data.destino.posicion.id', $posicion->id)
            ->assertJsonPath('data.destino.version_anterior', 0)
            ->assertJsonPath('data.destino.version_resultante', 1)
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/movimientos/ubicar', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $movimientoId);

        $this->assertSame(1, Folio::query()->count());
        $this->assertSame(1, Movimiento::query()->count());
        $this->assertDatabaseHas('folios', [
            'numero_folio' => 'FOLIO-NUEVO',
            'variedad' => 'Lapins',
            'calibre' => 'J',
            'origen_sistema' => 'manual',
        ]);
        $this->assertDatabaseHas('ubicaciones_actuales', [
            'posicion_id' => $posicion->id,
        ]);
    }

    public function test_ubicar_rechaza_campos_de_integracion_controlados_por_el_servidor(): void
    {
        [, , $token] = $this->crearIdentidad();
        [$camara, $posicion] = $this->crearCamara('CAM-01');
        $sesionId = $this->abrirSesion($token, $camara);

        $this->withToken($token)
            ->postJson('/api/movimientos/ubicar', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => 'FOLIO-SPOOF',
                'tipo_bulto' => 'pallet',
                'posicion_destino_id' => $posicion->id,
                'sesion_destino_id' => $sesionId,
                'version_destino_conocida' => 0,
                'generado_dispositivo_at' => now()->toAtomString(),
                'datos_folio' => [
                    'origen_sistema' => 'suit_export',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('datos_folio');

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'FOLIO-SPOOF']);
    }

    public function test_una_version_desactualizada_responde_conflicto_sin_mover_el_folio(): void
    {
        [, , $token] = $this->crearIdentidad();
        [$camara, $origen, $destino] = $this->crearCamara('CAM-01', 2);
        $sesionId = $this->abrirSesion($token, $camara);
        $folioId = $this->ubicar($token, $origen, $sesionId, 0, 'FOLIO-0001');

        $this->withToken($token)
            ->postJson('/api/movimientos/mover', [
                'operacion_id' => (string) Str::uuid(),
                'folio_id' => $folioId,
                'posicion_destino_id' => $destino->id,
                'sesion_origen_id' => $sesionId,
                'sesion_destino_id' => $sesionId,
                'version_origen_conocida' => 0,
                'version_destino_conocida' => 0,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folioId,
            'posicion_id' => $origen->id,
        ]);
        $this->assertSame(1, $camara->refresh()->version_plano);
    }

    public function test_traslada_entre_camaras_y_filtra_los_movimientos_recientes(): void
    {
        [$usuario, $dispositivo, $token] = $this->crearIdentidad();
        [$camaraOrigen, $posicionOrigen] = $this->crearCamara('CAM-01');
        [$camaraDestino, $posicionDestino] = $this->crearCamara('CAM-02');
        [$otraCamara] = $this->crearCamara('CAM-03');
        $sesionOrigenId = $this->abrirSesion($token, $camaraOrigen);
        $sesionDestinoId = $this->abrirSesion($token, $camaraDestino);
        $folioId = $this->ubicar(
            $token,
            $posicionOrigen,
            $sesionOrigenId,
            0,
            'FOLIO-TRASLADO',
        );

        $this->withToken($token)
            ->postJson('/api/movimientos/mover', [
                'operacion_id' => (string) Str::uuid(),
                'folio_id' => $folioId,
                'posicion_destino_id' => $posicionDestino->id,
                'sesion_origen_id' => $sesionOrigenId,
                'sesion_destino_id' => $sesionDestinoId,
                'version_origen_conocida' => 1,
                'version_destino_conocida' => 0,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo_movimiento', 'traslado_entre_camaras')
            ->assertJsonPath('data.origen.camara.id', $camaraOrigen->id)
            ->assertJsonPath('data.destino.camara.id', $camaraDestino->id)
            ->assertJsonPath('data.usuario.id', $usuario->id);

        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folioId,
            'posicion_id' => $posicionDestino->id,
        ]);
        $this->assertDatabaseHas('movimientos', [
            'folio_id' => $folioId,
            'dispositivo_id' => $dispositivo->id,
            'tipo_movimiento' => 'traslado_entre_camaras',
        ]);

        $this->withToken($token)
            ->getJson("/api/movimientos/recientes?camara_id={$camaraDestino->id}&limite=10")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo_movimiento', 'traslado_entre_camaras');

        $this->withToken($token)
            ->getJson("/api/movimientos/recientes?camara_id={$otraCamara->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array{User, Dispositivo, string}
     */
    private function crearIdentidad(): array
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet 01',
        ]);
        $token = $usuario
            ->crearTokenParaDispositivo($dispositivo, 'tablet-01')
            ->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    /**
     * @return array<int, Camara|Posicion>
     */
    private function crearCamara(string $codigo, int $cantidadPosiciones = 1): array
    {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
        ]);
        $resultado = [$camara];

        for ($indice = 1; $indice <= $cantidadPosiciones; $indice++) {
            $resultado[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $indice,
                'nivel' => 1,
                'etiqueta' => "A-{$indice}-1",
            ]);
        }

        return $resultado;
    }

    private function abrirSesion(string $token, Camara $camara): string
    {
        return $this->withToken($token)
            ->postJson("/api/camaras/{$camara->id}/sesiones")
            ->assertCreated()
            ->json('data.id');
    }

    private function ubicar(
        string $token,
        Posicion $posicion,
        string $sesionId,
        int $version,
        string $numeroFolio,
    ): string {
        return $this->withToken($token)
            ->postJson('/api/movimientos/ubicar', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => $numeroFolio,
                'tipo_bulto' => 'pallet',
                'posicion_destino_id' => $posicion->id,
                'sesion_destino_id' => $sesionId,
                'version_destino_conocida' => $version,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertOk()
            ->json('data.folio.id');
    }
}
