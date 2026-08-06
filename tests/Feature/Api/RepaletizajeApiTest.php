<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepaletizajeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_pallet_nuevo_con_mix_y_conserva_saldo_residual(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio($temporada, 'SAL-001', 90, calibre: '2J', csg: '111');
        $segundo = $this->folio($temporada, 'SAL-002', 40, calibre: '3J', csg: '222');

        $respuesta = $this->withToken($token)->postJson('/api/validacion/repaletizajes', [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => 'pallet',
            'estrategia_folio' => 'nuevo',
            'numero_folio_resultante' => 'PAL-900',
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 90],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 30],
            ],
        ])->assertOk()
            ->assertJsonPath('data.folio_resultante.numero_folio', 'PAL-900')
            ->assertJsonPath('data.folio_resultante.cantidad_cajas', 120)
            ->assertJsonPath('data.folio_resultante.tipo_bulto', 'pallet')
            ->assertJsonPath('data.folio_resultante.calibre', 'MIX')
            ->assertJsonPath('data.folio_resultante.csg', 'MIX');

        $this->assertDatabaseHas('folios', [
            'id' => $primero->id,
            'activo' => false,
            'estado_operacional' => 'agotado',
        ]);
        $this->assertSame(
            10,
            (int) Folio::query()->findOrFail($segundo->id)->datos_externos['cantidad_cajas'],
        );
        $this->assertDatabaseCount('repaletizaje_detalles', 2);
        $this->assertContains('calibre', $respuesta->json('data.campos_mix'));
        $this->assertContains('csg', $respuesta->json('data.campos_mix'));
    }

    public function test_consolida_saldo_post_prefrio_conservando_folio_disponible(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio(
            $temporada,
            'SAL-FRIO-1',
            30,
            condicion: CondicionTermicaFolio::PrefrioAprobado,
            estado: EstadoOperacionalFolio::Disponible,
        );
        $segundo = $this->folio(
            $temporada,
            'SAL-FRIO-2',
            25,
            condicion: CondicionTermicaFolio::PrefrioAprobado,
            estado: EstadoOperacionalFolio::Disponible,
        );

        $this->withToken($token)->postJson('/api/validacion/repaletizajes', [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => 'saldo',
            'estrategia_folio' => 'conservar',
            'numero_folio_resultante' => 'SAL-FRIO-1',
            'folio_conservado_id' => $primero->id,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 30],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 25],
            ],
        ])->assertOk()
            ->assertJsonPath('data.folio_resultante.numero_folio', 'SAL-FRIO-1')
            ->assertJsonPath('data.folio_resultante.cantidad_cajas', 55)
            ->assertJsonPath('data.folio_resultante.tipo_bulto', 'saldo')
            ->assertJsonPath('data.folio_resultante.estado_operacional', 'disponible')
            ->assertJsonPath('data.folio_resultante.condicion_termica', 'prefrio_aprobado');
    }

    public function test_bloquea_clientes_diferentes(): void
    {
        $this->assertIncompatibilidad('cliente', ['cliente' => 'OTRO CLIENTE']);
    }

    public function test_bloquea_especies_diferentes(): void
    {
        $this->assertIncompatibilidad('especie', ['especie' => 'Kiwi']);
    }

    public function test_bloquea_marcas_diferentes(): void
    {
        $this->assertIncompatibilidad('marca', ['marca' => 'OTRA MARCA']);
    }

    public function test_bloquea_estados_termicos_diferentes(): void
    {
        $this->assertIncompatibilidad('estado térmico', [
            'condicion' => CondicionTermicaFolio::PrefrioAprobado,
            'estado' => EstadoOperacionalFolio::Disponible,
        ]);
    }

    public function test_es_idempotente_y_anulacion_restaura_los_folios(): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio($temporada, 'SAL-R1', 60);
        $segundo = $this->folio($temporada, 'SAL-R2', 70);
        $operacion = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacion,
            'tipo_resultado' => 'pallet',
            'estrategia_folio' => 'conservar',
            'numero_folio_resultante' => 'SAL-R1',
            'folio_conservado_id' => $primero->id,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 60],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 60],
            ],
        ];

        $id = $this->withToken($token)
            ->postJson('/api/validacion/repaletizajes', $payload)
            ->assertOk()
            ->json('data.id');
        $this->withToken($token)
            ->postJson('/api/validacion/repaletizajes', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('repaletizajes', 1);

        $supervisorToken = $this->token(RolUsuario::SupervisorFrio, 'SUP-REPA');
        $this->withToken($supervisorToken)->postJson(
            "/api/validacion/repaletizajes/{$id}/anular",
            [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Error operacional confirmado.',
            ],
        )->assertOk()->assertJsonPath('data.estado', 'anulado');

        $this->assertSame(
            60,
            (int) Folio::query()->findOrFail($primero->id)->datos_externos['cantidad_cajas'],
        );
        $this->assertSame(
            70,
            (int) Folio::query()->findOrFail($segundo->id)->datos_externos['cantidad_cajas'],
        );
        $this->assertSame(
            'saldo',
            Folio::query()->findOrFail($primero->id)->tipo_bulto->value,
        );
    }

    /** @param array<string, mixed> $cambio */
    private function assertIncompatibilidad(string $campo, array $cambio): void
    {
        [$token, $temporada] = $this->contexto();
        $primero = $this->folio($temporada, 'SAL-A-'.$campo, 20);
        $segundo = $this->folio(
            $temporada,
            'SAL-B-'.$campo,
            20,
            cliente: $cambio['cliente'] ?? 'CLIENTE',
            especie: $cambio['especie'] ?? 'Cereza',
            marca: $cambio['marca'] ?? 'MARCA',
            condicion: $cambio['condicion'] ?? CondicionTermicaFolio::PendientePrefrio,
            estado: $cambio['estado'] ?? EstadoOperacionalFolio::PendientePrefrio,
        );

        $this->withToken($token)->postJson('/api/validacion/repaletizajes', [
            'operacion_id' => (string) Str::uuid(),
            'tipo_resultado' => 'saldo',
            'estrategia_folio' => 'nuevo',
            'numero_folio_resultante' => 'SAL-MIX-'.$campo,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 20],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 20],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                "No se puede mezclar diferente {$campo} en un repaletizaje.",
            );
    }

    /** @return array{string, Temporada} */
    private function contexto(): array
    {
        $temporada = Temporada::query()->firstOrCreate(
            ['codigo' => '2026-2027'],
            [
                'nombre' => 'Temporada',
                'activa' => true,
                'version_catalogo' => 1,
            ],
        );

        return [$this->token(RolUsuario::Validador, 'VAL-'.Str::random(6)), $temporada];
    }

    private function token(RolUsuario $rol, string $codigo): string
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);

        return $usuario
            ->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")
            ->plainTextToken;
    }

    private function folio(
        Temporada $temporada,
        string $numero,
        int $cantidad,
        string $cliente = 'CLIENTE',
        string $especie = 'Cereza',
        string $marca = 'MARCA',
        string $calibre = '2J',
        string $csg = '111',
        CondicionTermicaFolio $condicion = CondicionTermicaFolio::PendientePrefrio,
        EstadoOperacionalFolio $estado = EstadoOperacionalFolio::PendientePrefrio,
    ): Folio {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => mb_strtoupper($numero),
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => $estado,
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => $condicion === CondicionTermicaFolio::PrefrioAprobado
                ? HabilitacionAlmacenamientoFolio::Habilitado
                : HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'variedad' => 'Santina',
            'calibre' => $calibre,
            'marca' => $marca,
            'exportadora' => $cliente,
            'origen_sistema' => 'validacion',
            'identificador_externo' => (string) Str::uuid(),
            'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
            'datos_externos' => [
                'especie' => $especie,
                'categoria' => 'Exportación',
                'envase' => 'Caja 5 kg',
                'csg' => $csg,
                'predio' => 'Predio',
                'cuartel' => 'Cuartel',
                'cantidad_cajas' => $cantidad,
            ],
        ]);
    }
}
