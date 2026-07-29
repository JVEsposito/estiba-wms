<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PosicionTunelPrefrio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlujoValidacionPrefrioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_folio_aprobado_en_validacion_aparece_en_prefrio_y_sale_al_cargarlo(): void
    {
        $catalogo = $this->crearCatalogoValidacion();
        [, $tokenValidador] = $this->acceso(RolUsuario::Validador, 'VAL-PF-01');
        [, $tokenPrefrio] = $this->acceso(RolUsuario::OperadorPrefrio, 'PF-FLUJO-01');
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);

        $this->conToken($tokenValidador)
            ->postJson('/api/validacion/pallets', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => 'PAL-VALIDADO-PF-001',
                'tipo_bulto' => 'pallet',
                'cantidad_cajas' => 120,
                'linea_proceso' => 1,
                'turno' => 'A',
                ...$catalogo,
                'resultado' => 'aprobado',
                'motivo' => null,
                'observacion' => null,
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.folio.estado_operacional', 'pendiente_prefrio');

        $folioId = Folio::query()
            ->where('numero_folio', 'PAL-VALIDADO-PF-001')
            ->firstOrFail()
            ->id;

        $this->conToken($tokenPrefrio)
            ->getJson('/api/prefrio/folios-disponibles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $folioId)
            ->assertJsonPath('data.0.numero_folio', 'PAL-VALIDADO-PF-001')
            ->assertJsonPath('data.0.condicion_termica', 'pendiente_prefrio')
            ->assertJsonPath('data.0.habilitacion_almacenamiento', 'no_habilitado')
            ->assertJsonPath('data.0.especie', 'Cereza')
            ->assertJsonPath('data.0.variedad', 'Santina')
            ->assertJsonPath('data.0.calibre', '2J')
            ->assertJsonPath('data.0.envase', 'Granel 5 kg')
            ->assertJsonPath('data.0.categoria', 'Exportación')
            ->assertJsonPath('data.0.exportadora', 'DIS')
            ->assertJsonPath('data.0.marca', 'ATLAS')
            ->assertJsonPath('data.0.csg', '105410')
            ->assertJsonPath('data.0.predio', 'OLM')
            ->assertJsonPath('data.0.cantidad_cajas', 120);

        $tunelId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/prefrio/tuneles', [
                'nombre' => 'Túnel flujo Validación Prefrío',
                'capacidad_posiciones' => 20,
                'setpoint_habitual' => -1.5,
                'estado_tecnico' => 'operativo',
            ])
            ->assertCreated()
            ->json('data.id');
        $posicionId = PosicionTunelPrefrio::query()
            ->where('tunel_prefrio_id', $tunelId)
            ->orderBy('numero')
            ->firstOrFail()
            ->id;

        $proceso = $this->conToken($tokenPrefrio)
            ->postJson('/api/prefrio/procesos', [
                'operacion_id' => (string) Str::uuid(),
                'tunel_prefrio_id' => $tunelId,
                'setpoint' => -1.5,
                'duracion_objetivo_minutos' => 720,
                'formato_referencia' => 'Granel 5 kg',
                'ocurrido_at' => now()->toAtomString(),
            ])
            ->assertCreated()
            ->json('data');

        $this->conToken($tokenPrefrio)
            ->postJson("/api/prefrio/procesos/{$proceso['id']}/folios", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 0,
                'folio_id' => $folioId,
                'posicion_tunel_prefrio_id' => $posicionId,
                'temperatura_inicial' => 8.6,
                'ocurrido_at' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cargando')
            ->assertJsonPath('data.folios.0.folio.numero_folio', 'PAL-VALIDADO-PF-001');

        $this->conToken($tokenPrefrio)
            ->getJson('/api/prefrio/folios-disponibles')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array<string, string|int>
     */
    private function crearCatalogoValidacion(): array
    {
        $temporada = (string) Str::uuid();
        $articulo = (string) Str::uuid();
        $origen = (string) Str::uuid();
        $categoria = (string) Str::uuid();

        DB::table('temporadas')->update(['activa' => false]);
        DB::table('temporadas')->insert([
            'id' => $temporada,
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
            'version_catalogo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('articulos_validacion')->insert([
            'id' => $articulo,
            'temporada_id' => $temporada,
            'especie' => 'Cereza',
            'variedad' => 'Santina',
            'calibre' => '2J',
            'envase' => 'Granel 5 kg',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categorias_validacion')->insert([
            'id' => $categoria,
            'temporada_id' => $temporada,
            'nombre' => 'Exportación',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('origenes_validacion')->insert([
            'id' => $origen,
            'temporada_id' => $temporada,
            'cliente' => 'DIS',
            'marca' => 'ATLAS',
            'csg' => '105410',
            'predio' => 'OLM',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('combinaciones_validacion')->insert([
            'id' => (string) Str::uuid(),
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'categoria_validacion_id' => $categoria,
            'catalogo_version' => 1,
        ];
    }

    /**
     * @return array{User, string}
     */
    private function acceso(RolUsuario $rol, string $codigo): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo(
            $dispositivo,
            "test-{$codigo}",
        )->plainTextToken;

        return [$usuario, $token];
    }

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
