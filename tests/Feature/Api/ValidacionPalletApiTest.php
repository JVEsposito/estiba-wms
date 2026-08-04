<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ValidacionPalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_aprueba_y_crea_folio_pendiente_de_prefrio_de_forma_idempotente(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-01');
        $payload = $this->payload($catalogo, 'PAL-0001');

        $validacion = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.numero_folio', 'PAL-0001')
            ->assertJsonPath('data.resultado', 'aprobado')
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.linea_proceso', 1)
            ->assertJsonPath('data.turno', 'A')
            ->assertJsonPath('data.catalogo.categoria.nombre', 'Exportación')
            ->assertJsonPath('data.folio.estado_operacional', 'pendiente_prefrio')
            ->json('data.id');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $validacion);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0001')->count());
        $this->assertDatabaseHas('folios', [
            'numero_folio' => 'PAL-0001',
            'temporada_id' => $catalogo['temporada_id'],
        ]);
        $this->assertDatabaseCount('validaciones_pallet', 1);
    }

    public function test_observa_sin_crear_folio_y_la_aprobacion_posterior_es_otro_intento(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-02');
        $observado = [
            ...$this->payload($catalogo, 'PAL-0002'),
            'resultado' => 'observado',
            'motivo' => 'csg_no_coincide',
            'observacion' => 'La etiqueta física informa otro CSG.',
        ];

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $observado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 1)
            ->assertJsonPath('data.folio', null);

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0002']);

        $aprobado = $this->payload($catalogo, 'PAL-0002');
        $aprobado['operacion_id'] = (string) Str::uuid();

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $aprobado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 2)
            ->assertJsonPath('data.folio.numero_folio', 'PAL-0002');
    }

    public function test_mi_sesion_resume_folios_intentos_y_no_mezcla_validadores_dispositivos_ni_accesos_previos(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-SES-01');

        $anterior = $this->payload($catalogo, 'PAL-SES-ANTERIOR');
        $anterior['generado_dispositivo_at'] = now()->subMinute()->toAtomString();
        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $anterior)
            ->assertCreated();

        $observado = [
            ...$this->payload($catalogo, 'PAL-SES-0001'),
            'resultado' => 'observado',
            'motivo' => 'csg_no_coincide',
            'observacion' => 'Primer intento de la sesión.',
        ];
        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $observado)
            ->assertCreated();

        $aprobado = $this->payload($catalogo, 'PAL-SES-0001');
        $aprobado['operacion_id'] = (string) Str::uuid();
        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $aprobado)
            ->assertCreated()
            ->assertJsonPath('data.numero_intento', 2);

        $segundoObservado = [
            ...$this->payload($catalogo, 'PAL-SES-0002'),
            'resultado' => 'observado',
            'motivo' => 'etiqueta_no_coincide',
            'observacion' => 'Segundo folio de la sesión.',
        ];
        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $segundoObservado)
            ->assertCreated();

        [, $otroToken] = $this->acceso(RolUsuario::Validador, 'VAL-SES-02');
        $this->conToken($otroToken)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-SES-OTRO'))
            ->assertCreated();

        $this->conToken($token)
            ->getJson('/api/validacion/pallets/mi-sesion?per_page=10')
            ->assertOk()
            ->assertJsonPath('sesion.dispositivo.codigo', 'VAL-SES-01')
            ->assertJsonPath('sesion.temporada.id', $catalogo['temporada_id'])
            ->assertJsonPath('resumen.folios_trabajados', 2)
            ->assertJsonPath('resumen.registros_realizados', 3)
            ->assertJsonPath('resumen.aprobados', 1)
            ->assertJsonPath('resumen.observados', 1)
            ->assertJsonPath('resumen.rechazados', 0)
            ->assertJsonPath('resumen.conflictos', 0)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['numero_folio' => 'PAL-SES-ANTERIOR'])
            ->assertJsonMissing(['numero_folio' => 'PAL-SES-OTRO']);
    }

    public function test_aprobacion_es_terminal_y_un_intento_posterior_queda_en_conflicto(): void
    {
        [$catalogo, $tokenA] = $this->contexto(RolUsuario::Validador, 'VAL-03');
        [, $tokenB] = $this->acceso(RolUsuario::Validador, 'VAL-04');

        $primeraId = $this->conToken($tokenA)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0003'))
            ->assertCreated()
            ->json('data.id');

        $segundo = [
            ...$this->payload($catalogo, 'PAL-0003'),
            'resultado' => 'observado',
            'motivo' => 'etiqueta_no_coincide',
            'observacion' => 'Segundo dispositivo informa otra etiqueta.',
        ];

        $this->conToken($tokenB)
            ->postJson('/api/validacion/pallets', $segundo)
            ->assertStatus(409)
            ->assertJsonPath('data.estado', 'conflicto')
            ->assertJsonPath('data.numero_intento', 2)
            ->assertJsonPath('data.conflicto_con.id', $primeraId);

        $this->assertSame(1, Folio::query()->where('numero_folio', 'PAL-0003')->count());
        $this->assertDatabaseCount('validaciones_pallet', 2);
    }

    public function test_supervisor_puede_rechazar_y_el_rechazo_es_terminal(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::SupervisorFrio, 'SUP-01');
        $rechazo = [
            ...$this->payload($catalogo, 'PAL-0004'),
            'resultado' => 'rechazado',
            'motivo' => 'condicion_fruta',
            'observacion' => 'Condición no aceptable.',
        ];

        $rechazoId = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $rechazo)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.folio', null)
            ->json('data.id');

        $aprobacion = $this->payload($catalogo, 'PAL-0004');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $aprobacion)
            ->assertStatus(409)
            ->assertJsonPath('data.estado', 'conflicto')
            ->assertJsonPath('data.conflicto_con.id', $rechazoId);

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0004']);
    }

    public function test_validador_no_puede_confirmar_rechazo_definitivo(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-05');
        $payload = [
            ...$this->payload($catalogo, 'PAL-0005'),
            'resultado' => 'rechazado',
            'motivo' => 'condicion_fruta',
            'observacion' => 'Condición no aceptable.',
        ];

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('validaciones_pallet', ['numero_folio' => 'PAL-0005']);
    }

    public function test_reutilizar_uuid_con_payload_distinto_genera_conflicto(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-06');
        $payload = [
            ...$this->payload($catalogo, 'PAL-0006'),
            'resultado' => 'observado',
            'motivo' => 'cantidad_cajas_incorrecta',
            'observacion' => 'Cantidad pendiente de confirmación.',
        ];

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated();

        $payload['cantidad_cajas'] = 121;

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->assertDatabaseCount('validaciones_pallet', 1);
    }

    public function test_acepta_catalogo_desactualizado_y_lo_informa_en_la_respuesta(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-07');
        $payload = $this->payload($catalogo, 'PAL-0007');
        $payload['catalogo_version'] = 2;

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('catalogo_desactualizado', true)
            ->assertJsonPath('data.catalogo.desactualizado', true)
            ->assertJsonPath('data.catalogo.version_dispositivo', 2)
            ->assertJsonPath('data.catalogo.version_servidor', 1);
    }

    public function test_rechaza_una_temporada_inactiva(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-08');
        DB::table('temporadas')
            ->where('id', $catalogo['temporada_id'])
            ->update(['activa' => false]);

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0008'))
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'PAL-0008']);
    }

    public function test_indice_valida_paginacion_filtra_y_no_expone_el_hash_interno(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-09');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-0009'))
            ->assertCreated();

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?per_page=1000')
            ->assertUnprocessable();

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?folio=pal-0009&resultado=aprobado&estado=aceptada&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.numero_folio', 'PAL-0009')
            ->assertJsonMissingPath('data.0.payload_hash');
    }

    public function test_exige_contexto_de_jornada_para_registrar_la_validacion(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-JORNADA-01');
        $payload = $this->payload($catalogo, 'PAL-SIN-JORNADA');
        unset($payload['linea_proceso'], $payload['turno']);

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['linea_proceso', 'turno']);
    }

    public function test_filtra_por_jornada_y_exporta_el_registro_rrpp_01_en_hora_local(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-RRPP-01');
        $payload = [
            ...$this->payload($catalogo, 'PAL-RRPP-0001'),
            'linea_proceso' => 2,
            'turno' => 'b',
            'generado_dispositivo_at' => '2026-07-29T23:30:00-04:00',
        ];

        $encargado = $this->conToken($token)
            ->postJson('/api/validacion/pallets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.linea_proceso', 2)
            ->assertJsonPath('data.turno', 'B')
            ->json('data.usuario.nombre');

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?fecha=2026-07-29&linea_proceso=2&turno=B&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.numero_folio', 'PAL-RRPP-0001');

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?fecha=2026-07-30&per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->conToken($token)
            ->getJson("/api/validacion/registro/opciones?temporada_id={$catalogo['temporada_id']}")
            ->assertOk()
            ->assertJsonPath('temporada.id', $catalogo['temporada_id'])
            ->assertJsonCount(1, 'validadores')
            ->assertJsonPath('validadores.0.nombre', $encargado);

        $respuesta = $this->conToken($token)->get(
            '/api/validacion/registro/rrpp-01?'.http_build_query([
                'temporada_id' => $catalogo['temporada_id'],
                'fecha' => '2026-07-29',
                'linea_proceso' => 2,
                'turno' => 'B',
            ]),
        );

        $respuesta
            ->assertOk()
            ->assertDownload('RRPP-01_2026-07-29.xlsx')
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()) === true);
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($hoja);
        $this->assertSame('29-07-2026', $this->valorCelda($hoja, 'C5'));
        $this->assertSame($encargado, $this->valorCelda($hoja, 'C6'));
        $this->assertSame('B', $this->valorCelda($hoja, 'C7'));
        $this->assertSame('2', $this->valorCelda($hoja, 'C8'));
        $this->assertSame('PAL-RRPP-0001', $this->valorCelda($hoja, 'B11'));
        $this->assertSame('ATLAS', $this->valorCelda($hoja, 'C11'));
        $this->assertSame('5 kg', $this->valorCelda($hoja, 'D11'));
        $this->assertSame('Cereza', $this->valorCelda($hoja, 'E11'));
        $this->assertSame('Santina', $this->valorCelda($hoja, 'F11'));
        $this->assertSame('105410', $this->valorCelda($hoja, 'G11'));
        $this->assertSame('2J', $this->valorCelda($hoja, 'H11'));
        $this->assertSame('120', $this->valorCelda($hoja, 'I11'));
        $this->assertSame('X', $this->valorCelda($hoja, 'J11'));
        $this->assertSame('', $this->valorCelda($hoja, 'K11'));
        $this->assertSame('SUM(I11:I30)', $this->formulaCelda($hoja, 'I31'));
        $this->assertSame('120', $this->valorCelda($hoja, 'I31'));
        $zip->close();
    }

    public function test_historial_separa_temporadas_y_por_defecto_muestra_solo_la_activa(): void
    {
        [$catalogo, $token] = $this->contexto(RolUsuario::Validador, 'VAL-TEMP-01');

        $this->conToken($token)
            ->postJson('/api/validacion/pallets', $this->payload($catalogo, 'PAL-TEMP-ANTERIOR'))
            ->assertCreated();

        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'TEMP-NUEVA',
            'nombre' => 'Temporada nueva',
            'activa' => true,
        ]);

        $this->assertFalse(Temporada::query()->findOrFail($catalogo['temporada_id'])->activa);

        $this->conToken($token)
            ->getJson('/api/validacion/pallets?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->conToken($token)
            ->getJson("/api/validacion/pallets?temporada_id={$catalogo['temporada_id']}&per_page=10")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.numero_folio', 'PAL-TEMP-ANTERIOR');
    }

    public function test_validador_no_puede_consultar_cargas_materiales_ni_camaras(): void
    {
        [, $token] = $this->acceso(RolUsuario::Validador, 'VAL-10');

        $this->conToken($token)
            ->getJson('/api/camaras')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->conToken($token)
            ->getJson('/api/cargas')
            ->assertForbidden();

        $this->conToken($token)
            ->getJson('/api/materiales/inventario')
            ->assertForbidden();
    }

    /**
     * @return array{array<string, string|int>, string}
     */
    private function contexto(RolUsuario $rol, string $codigo): array
    {
        $temporada = (string) Str::uuid();
        $articulo = (string) Str::uuid();
        $origen = (string) Str::uuid();
        $categoria = (string) Str::uuid();

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
            'envase' => '5 kg',
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
        [, $token] = $this->acceso($rol, $codigo);

        return [[
            'temporada_id' => $temporada,
            'articulo_validacion_id' => $articulo,
            'origen_validacion_id' => $origen,
            'categoria_validacion_id' => $categoria,
            'catalogo_version' => 1,
        ], $token];
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
        $token = $usuario->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")->plainTextToken;

        return [$usuario, $token];
    }

    /**
     * @param  array<string, string|int>  $catalogo
     * @return array<string, mixed>
     */
    private function payload(array $catalogo, string $folio): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'numero_folio' => $folio,
            'tipo_bulto' => 'pallet',
            'cantidad_cajas' => 120,
            'linea_proceso' => 1,
            'turno' => 'A',
            ...$catalogo,
            'resultado' => 'aprobado',
            'motivo' => null,
            'observacion' => null,
            'generado_dispositivo_at' => now()->toAtomString(),
        ];
    }

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function valorCelda(string $xml, string $referencia): string
    {
        $documento = new \DOMDocument;
        $this->assertTrue($documento->loadXML($xml));
        $xpath = new \DOMXPath($documento);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $celda = $xpath->query("//m:c[@r='{$referencia}']")->item(0);
        $this->assertNotNull($celda);
        $inline = $xpath->query('m:is/m:t', $celda)->item(0);

        return $inline?->textContent ?? $xpath->query('m:v', $celda)->item(0)?->textContent ?? '';
    }

    private function formulaCelda(string $xml, string $referencia): string
    {
        $documento = new \DOMDocument;
        $this->assertTrue($documento->loadXML($xml));
        $xpath = new \DOMXPath($documento);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $celda = $xpath->query("//m:c[@r='{$referencia}']")->item(0);
        $this->assertNotNull($celda);

        return $xpath->query('m:f', $celda)->item(0)?->textContent ?? '';
    }
}
