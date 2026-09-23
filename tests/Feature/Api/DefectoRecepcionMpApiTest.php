<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DefectoRecepcionMpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_fotos_privadas_y_recupera_el_mismo_defecto_al_reintentar(): void
    {
        Storage::fake('local');
        [$recepcion, $validador, $dispositivo] = $this->prepararRecepcionTomada();
        $ruta = "/api/validacion-mp/recepciones/{$recepcion['id']}/defectos";
        $operacion = (string) Str::uuid();

        $resultado = $this->post($ruta, $this->entrada($operacion), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.numero_recepcion', $recepcion['numero_recepcion'])
            ->assertJsonPath('data.numero_guia_despacho', 'GD-DEF-001')
            ->assertJsonPath('data.cantidad_afectada', 2)
            ->assertJsonPath('data.categoria', 'envase_danado')
            ->assertJsonPath('data.dispositivo.codigo', $dispositivo->codigo)
            ->assertJsonCount(2, 'data.evidencias')
            ->json('data');
        $this->assertSame($validador->id, $resultado['validador']['id']);
        $this->assertDatabaseCount('defectos_recepcion_mp', 1);
        $this->assertDatabaseCount('evidencias_defecto_recepcion_mp', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles('defectos-recepcion-mp'));
        $this->assertSame('defecto', $resultado['evidencias'][0]['tipo']);
        $this->assertSame('guia', $resultado['evidencias'][1]['tipo']);

        $this->post($ruta, $this->entrada($operacion), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.id', $resultado['id']);
        $this->assertDatabaseCount('defectos_recepcion_mp', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles('defectos-recepcion-mp'));
        $this->post($ruta, $this->entrada($operacion, 'Otro defecto'), ['Accept' => 'application/json'])
            ->assertConflict();

        $this->getJson($ruta)->assertOk()->assertJsonCount(1, 'data');
        $this->get($resultado['evidencias'][0]['url'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('ruta', $resultado['evidencias'][0]);
    }

    public function test_solo_el_validador_asignado_desde_tablet_registrada_puede_ingresar_fotos(): void
    {
        Storage::fake('local');
        [$recepcion, $validador] = $this->prepararRecepcionTomada();
        $ruta = "/api/validacion-mp/recepciones/{$recepcion['id']}/defectos";

        $otro = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $this->actingAs($otro, 'sanctum')
            ->post($ruta, $this->entrada((string) Str::uuid()), ['Accept' => 'application/json'])
            ->assertForbidden();

        $oficina = $validador->createToken('oficina', ['oficina']);
        $validador->withAccessToken($oficina->accessToken);
        $this->actingAs($validador, 'sanctum')
            ->post($ruta, $this->entrada((string) Str::uuid()), ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->assertDatabaseCount('defectos_recepcion_mp', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('defectos-recepcion-mp'));
    }

    public function test_el_historial_de_temporada_anterior_y_sus_fotos_solo_son_visibles_a_auditores(): void
    {
        Storage::fake('local');
        [$recepcion, $validador] = $this->prepararRecepcionTomada();
        $defecto = $this->post("/api/validacion-mp/recepciones/{$recepcion['id']}/defectos",
            $this->entrada((string) Str::uuid()), ['Accept' => 'application/json'])
            ->assertCreated()->json('data');

        Temporada::query()->whereKey($defecto['temporada_id'])->update(['activa' => false]);
        $this->getJson("/api/validacion-mp/recepciones/{$recepcion['id']}/defectos")
            ->assertNotFound();
        $this->get($defecto['evidencias'][0]['url'])->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => RolUsuario::ValidadorMp]), 'sanctum')
            ->getJson('/api/materia-prima/defectos-recepcion?temporada_id='.$defecto['temporada_id'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => RolUsuario::SupervisorFrio]), 'sanctum')
            ->getJson('/api/materia-prima/defectos-recepcion?temporada_id='.$defecto['temporada_id'])
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $defecto['id']);
        $this->getJson('/api/materia-prima/defectos-recepcion/'.$defecto['id'])
            ->assertOk()->assertJsonPath('data.numero_guia_despacho', 'GD-DEF-001');
        $this->get($defecto['evidencias'][0]['url'])->assertOk();
    }

    public function test_rechaza_defectos_sin_evidencia_fotografica(): void
    {
        Storage::fake('local');
        [$recepcion] = $this->prepararRecepcionTomada();

        $this->postJson("/api/validacion-mp/recepciones/{$recepcion['id']}/defectos", [
            'operacion_id' => (string) Str::uuid(),
            'categoria' => 'envase_danado',
            'descripcion' => 'Dos bins rotos al descargar',
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotografias']);
        $this->assertDatabaseCount('defectos_recepcion_mp', 0);
    }

    public function test_una_evidencia_no_se_puede_leer_usando_otro_defecto(): void
    {
        Storage::fake('local');
        [$recepcion] = $this->prepararRecepcionTomada();
        $ruta = "/api/validacion-mp/recepciones/{$recepcion['id']}/defectos";
        $primero = $this->post($ruta, $this->entrada((string) Str::uuid()), ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $segundo = $this->post($ruta, $this->entrada((string) Str::uuid(), 'Otro bin roto'), ['Accept' => 'application/json'])
            ->assertCreated()->json('data');

        $this->get("/api/materia-prima/defectos-recepcion/{$segundo['id']}/evidencias/{$primero['evidencias'][0]['id']}")
            ->assertNotFound();
        $this->assertDatabaseCount('defectos_recepcion_mp', 2);
    }

    /** @return array{0: array<string, mixed>, 1: User, 2: Dispositivo} */
    private function prepararRecepcionTomada(): array
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create(['codigo' => 'CLI-DEF', 'nombre' => 'Cliente defectos', 'activo' => true]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'PDA-DEF-01', 'nombre' => 'PDA de validación', 'plataforma' => 'android', 'activo' => true,
        ]);
        $recepcion = $this->actingAs($operador, 'sanctum')->postJson('/api/romana/recepciones', [
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => $temporada->id,
            'cliente_id' => $cliente->id,
            'tipo_recepcion' => 'fruta_con_envases',
            'tipo_servicio' => 'proceso',
            'envases' => [['tipo_envase' => 'bins', 'cantidad' => 10]],
            'numero_guia_despacho' => 'GD-DEF-001',
            'patente_camion' => 'ABCD12',
            'rut_conductor' => '12.345.678-5',
            'nombre_conductor' => 'Conductor',
            'peso_bruto' => 28000,
        ])->assertCreated()->json('data');

        $token = $validador->crearTokenParaDispositivo($dispositivo, 'pda', ['tablet:validacion_mp']);
        $validador->withAccessToken($token->accessToken);
        $this->actingAs($validador, 'sanctum')->postJson(
            "/api/validacion-mp/recepciones/{$recepcion['id']}/tomar",
            ['operacion_id' => (string) Str::uuid()],
        )->assertOk();

        return [$recepcion, $validador, $dispositivo];
    }

    /** @return array<string, mixed> */
    private function entrada(string $operacion, string $descripcion = 'Dos bins rotos al descargar'): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');

        return [
            'operacion_id' => $operacion,
            'categoria' => 'envase_danado',
            'tipo_envase' => 'bins',
            'cantidad_afectada' => 2,
            'descripcion' => $descripcion,
            'fotografias' => [UploadedFile::fake()->createWithContent('defecto.png', $png)],
            'fotografia_guia' => UploadedFile::fake()->createWithContent('guia.png', $png),
        ];
    }
}
