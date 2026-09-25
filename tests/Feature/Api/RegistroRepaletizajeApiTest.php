<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class RegistroRepaletizajeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tarjador_solo_ve_repaletizaje_en_la_pda_y_registra_la_repa_con_su_turno(): void
    {
        $catalogo = app(CatalogoModulosAcceso::class);
        $this->assertSame(['frigorifico.repaletizaje'], $catalogo->modulosPredeterminados(RolUsuario::Tarjador));
        $this->assertSame(['repaletizaje'], $catalogo->modulosTabletPredeterminados(RolUsuario::Tarjador));

        [$token, $tarjador] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-01');
        $temporada = $this->temporada();
        $primero = $this->folio($temporada, 'SAL-T-01', 60);
        $segundo = $this->folio($temporada, 'SAL-T-02', 60);

        $this->conToken($token)
            ->getJson('/api/validacion/repaletizajes/folios/SAL-T-01')
            ->assertOk()
            ->assertJsonPath('existe', true);

        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', $this->consolidacion($primero, $segundo, 'PAL-T-01', 'B'))
            ->assertOk()
            ->assertJsonPath('data.turno', 'B')
            ->assertJsonPath('data.fecha_operacional', now(config('app.operational_timezone'))->toDateString())
            ->assertJsonPath('data.operador.id', $tarjador->id);
    }

    public function test_la_repa_exige_turno_y_solo_admite_fecha_de_hoy_o_ayer(): void
    {
        [$token] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-02');
        $temporada = $this->temporada();
        $primero = $this->folio($temporada, 'SAL-T-03', 60);
        $segundo = $this->folio($temporada, 'SAL-T-04', 60);
        $payload = $this->consolidacion($primero, $segundo, 'PAL-T-02', 'A');

        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', [...$payload, 'turno' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('turno');

        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', [
                ...$payload,
                'fecha_operacional' => now(config('app.operational_timezone'))->subDays(3)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_operacional');

        // El turno de noche puede declarar la fecha de ayer.
        $ayer = now(config('app.operational_timezone'))->subDay()->toDateString();
        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', [...$payload, 'fecha_operacional' => $ayer])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_operacional');

        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', [...$payload, 'turno' => 'B', 'fecha_operacional' => 'ayer'])
            ->assertOk()
            ->assertJsonPath('data.fecha_operacional', $ayer)
            ->assertJsonPath('data.turno', 'B');
    }

    public function test_camarero_y_validador_mp_no_registran_repas_y_el_validador_conserva_su_acceso(): void
    {
        $temporada = $this->temporada();
        $primero = $this->folio($temporada, 'SAL-T-05', 60);
        $segundo = $this->folio($temporada, 'SAL-T-06', 60);
        $payload = $this->consolidacion($primero, $segundo, 'PAL-T-03', 'A');

        foreach ([RolUsuario::CamareroFrio, RolUsuario::ValidadorMp] as $indice => $rol) {
            [$token] = $this->acceso($rol, 'PDA-SIN-REPA-'.$indice);
            $this->conToken($token)->postJson('/api/validacion/repaletizajes', $payload)->assertForbidden();
        }

        [$tokenValidador] = $this->acceso(RolUsuario::Validador, 'PDA-VALIDADOR');
        $this->conToken($tokenValidador)->postJson('/api/validacion/repaletizajes', $payload)->assertOk();
    }

    public function test_la_repa_actualiza_la_version_del_plano_de_la_camara(): void
    {
        [$token] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-03');
        $temporada = $this->temporada();
        $primero = $this->folio($temporada, 'SAL-T-07', 60, CondicionTermicaFolio::PrefrioAprobado);
        $segundo = $this->folio($temporada, 'SAL-T-08', 60, CondicionTermicaFolio::PrefrioAprobado);
        $camara = Camara::create(['codigo' => 'CAM-REPA', 'nombre' => 'Cámara de repaletizaje']);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        UbicacionActual::create([
            'folio_id' => $primero->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]);
        $version = (int) $camara->refresh()->version_plano;

        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', $this->consolidacion($primero, $segundo, 'PAL-T-04', 'A'))
            ->assertOk();

        $this->assertGreaterThan($version, (int) $camara->refresh()->version_plano);
        $this->assertDatabaseHas('ubicaciones_actuales', [
            'camara_id' => $camara->id,
            'folio_id' => Folio::query()->where('numero_folio', 'PAL-T-04')->value('id'),
        ]);
    }

    public function test_la_oficina_lista_las_planillas_del_dia_y_descarga_el_rrpl_01_lleno(): void
    {
        [$tokenA, $tarjadorA] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-04', 'Ana Rojas');
        [$tokenB, $tarjadorB] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-05', 'Luis Soto');
        $temporada = $this->temporada();
        $origenes = collect(range(1, 6))->map(fn (int $numero): Folio => $this->folio($temporada, "SAL-P-{$numero}", 60));

        $this->conToken($tokenA)->postJson('/api/validacion/repaletizajes', $this->consolidacion($origenes[0], $origenes[1], 'PAL-P-01', 'A'))->assertOk();
        $anulable = $this->conToken($tokenA)
            ->postJson('/api/validacion/repaletizajes', $this->consolidacion($origenes[2], $origenes[3], 'PAL-P-02', 'A'))
            ->assertOk()
            ->json('data.id');
        $this->conToken($tokenB)->postJson('/api/validacion/repaletizajes', $this->consolidacion($origenes[4], $origenes[5], 'PAL-P-03', 'B'))->assertOk();

        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio, 'name' => 'Jefa de frigorífico']);
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/validacion/repaletizajes/{$anulable}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Folio resultante mal etiquetado',
            ])
            ->assertOk();

        $hoy = now(config('app.operational_timezone'))->toDateString();
        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/validacion/repaletizajes/registro/rrpl-01/planillas?fecha={$hoy}")
            ->assertOk()
            ->assertJsonPath('fecha', $hoy)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.turno', 'A')
            ->assertJsonPath('data.0.tarjador.nombre', 'Ana Rojas')
            ->assertJsonPath('data.0.repas', 2)
            ->assertJsonPath('data.0.anuladas', 1)
            ->assertJsonPath('data.1.turno', 'B')
            ->assertJsonPath('data.1.tarjador.nombre', 'Luis Soto');

        $hojas = $this->hojas($this->actingAs($supervisor, 'sanctum')
            ->get("/api/validacion/repaletizajes/registro/rrpl-01?fecha={$hoy}")
            ->assertOk());
        $this->assertCount(2, $hojas, 'Una hoja por turno y tarjador.');

        $hojaA = $hojas[0];
        $this->assertSame('A', $hojaA['B5']);
        $this->assertSame(now(config('app.operational_timezone'))->format('d-m-Y'), $hojaA['F5']);
        $this->assertSame('Ana Rojas', $hojaA['N5']);
        // Primer bloque: la repa vigente, con sus dos folios de origen.
        $this->assertSame('PAL-P-01', $hojaA['A8']);
        $this->assertStringStartsWith('REPA-', $hojaA['B22']);
        $this->assertSame('Santina', $hojaA['B16']);
        $this->assertSame('MARCA', $hojaA['B18']);
        $this->assertSame('Caja 5 kg', $hojaA['B20']);
        $this->assertSame('X', $hojaA['B25']);
        $this->assertArrayNotHasKey('C25', $hojaA);
        $this->assertSame('SAL-P-1', $hojaA['E8']);
        $this->assertSame('2J', $hojaA['H8']);
        $this->assertSame('111', $hojaA['I8']);
        $this->assertSame('60', $hojaA['J8']);
        $this->assertSame('SAL-P-2', $hojaA['E10']);
        $this->assertSame('120', $hojaA['J24']);
        // Segundo bloque: la repa anulada conserva su lugar en el documento.
        $this->assertStringContainsString('PAL-P-02', $hojaA['L8']);
        $this->assertStringContainsString('ANULADA', $hojaA['L8']);
        $this->assertStringContainsString('Folio resultante mal etiquetado', $hojaA['L8']);
        $this->assertSame('SAL-P-3', $hojaA['P8']);
        $this->assertArrayNotHasKey('A29', $hojaA);

        $this->assertSame('Luis Soto', $hojas[1]['N5']);
        $this->assertSame('PAL-P-03', $hojas[1]['A8']);

        $soloB = $this->hojas($this->actingAs($supervisor, 'sanctum')
            ->get("/api/validacion/repaletizajes/registro/rrpl-01?fecha={$hoy}&turno=B&user_id={$tarjadorB->id}")
            ->assertOk());
        $this->assertCount(1, $soloB);
        $this->assertSame('PAL-P-03', $soloB[0]['A8']);
        $this->assertNotSame($tarjadorA->id, $tarjadorB->id);
    }

    public function test_el_registro_en_blanco_se_descarga_y_un_dia_sin_repas_se_informa(): void
    {
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);

        $blanco = $this->hojas($this->actingAs($supervisor, 'sanctum')
            ->get('/api/validacion/repaletizajes/registro/rrpl-01/en-blanco')
            ->assertOk());
        $this->assertCount(1, $blanco);
        $this->assertArrayNotHasKey('A8', $blanco[0]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/validacion/repaletizajes/registro/rrpl-01?fecha=2020-01-01')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No existen repaletizajes para generar el registro RRPL-01.');

        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $this->actingAs($camarero, 'sanctum')
            ->get('/api/validacion/repaletizajes/registro/rrpl-01/en-blanco')
            ->assertForbidden();
    }

    public function test_las_planillas_y_la_descarga_no_mezclan_repas_de_temporadas_distintas(): void
    {
        [$token] = $this->acceso(RolUsuario::Tarjador, 'PDA-TARJA-TEMP');
        $anterior = $this->temporada();
        $primero = $this->folio($anterior, 'SAL-TEMP-01', 60);
        $segundo = $this->folio($anterior, 'SAL-TEMP-02', 60);
        $this->conToken($token)
            ->postJson('/api/validacion/repaletizajes', $this->consolidacion($primero, $segundo, 'PAL-TEMP-01', 'A'))
            ->assertOk();

        $anterior->update(['activa' => false]);
        Temporada::create([
            'codigo' => 'RRPL-TEMP-ACTUAL',
            'nombre' => 'Temporada activa RRPL',
            'activa' => true,
            'version_catalogo' => 1,
        ]);

        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $hoy = now(config('app.operational_timezone'))->toDateString();
        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/validacion/repaletizajes/registro/rrpl-01/planillas?fecha={$hoy}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/validacion/repaletizajes/registro/rrpl-01?fecha={$hoy}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No existen repaletizajes para generar el registro RRPL-01.');
    }

    /** @return array<string, mixed> */
    private function consolidacion(Folio $primero, Folio $segundo, string $resultado, string $turno): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'turno' => $turno,
            'tipo_resultado' => 'pallet',
            'estrategia_folio' => 'nuevo',
            'numero_folio_resultante' => $resultado,
            'cantidad_objetivo' => 120,
            'origenes' => [
                ['folio_id' => $primero->id, 'cantidad_aportada' => 60],
                ['folio_id' => $segundo->id, 'cantidad_aportada' => 60],
            ],
        ];
    }

    /**
     * Celdas con valor de cada hoja del libro descargado.
     *
     * @return array<int, array<string, string>>
     */
    private function hojas(TestResponse $respuesta): array
    {
        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);
        $hojas = [];
        for ($numero = 1; ($xml = $zip->getFromName("xl/worksheets/sheet{$numero}.xml")) !== false; $numero++) {
            $documento = simplexml_load_string($xml);
            $documento->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $celdas = [];
            foreach ($documento->xpath('//m:c[m:is or m:v]') as $celda) {
                $celda->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $texto = $celda->xpath('m:is/m:t');
                $valor = $texto ? (string) $texto[0] : (string) ($celda->xpath('m:v')[0] ?? '');
                if ((string) $celda['t'] === 's') {
                    continue;
                }
                $celdas[(string) $celda['r']] = $valor;
            }
            $hojas[] = $celdas;
        }
        $zip->close();

        return $hojas;
    }

    /** @return array{string, User} */
    private function acceso(RolUsuario $rol, string $codigo, ?string $nombre = null): array
    {
        $usuario = User::factory()->create(['rol' => $rol, ...($nombre ? ['name' => $nombre] : [])]);
        $dispositivo = Dispositivo::create([
            'codigo' => $codigo,
            'nombre' => "PDA {$codigo}",
            'plataforma' => 'android',
            'activo' => true,
        ]);

        return [$usuario->crearTokenParaDispositivo($dispositivo, "test-{$codigo}")->plainTextToken, $usuario];
    }

    private function conToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function temporada(): Temporada
    {
        return Temporada::query()->where('activa', true)->firstOrFail();
    }

    private function folio(
        Temporada $temporada,
        string $numero,
        int $cantidad,
        CondicionTermicaFolio $condicion = CondicionTermicaFolio::PendientePrefrio,
    ): Folio {
        $aprobado = $condicion === CondicionTermicaFolio::PrefrioAprobado;

        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => mb_strtoupper($numero),
            'tipo_bulto' => TipoBulto::Saldo,
            'estado_operacional' => $aprobado ? EstadoOperacionalFolio::Disponible : EstadoOperacionalFolio::PendientePrefrio,
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => $aprobado
                ? HabilitacionAlmacenamientoFolio::Habilitado
                : HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
            'variedad' => 'Santina',
            'calibre' => '2J',
            'marca' => 'MARCA',
            'exportadora' => 'CLIENTE',
            'origen_sistema' => 'validacion',
            'identificador_externo' => (string) Str::uuid(),
            'estado_integracion' => EstadoIntegracionFolio::NoVinculado,
            'datos_externos' => [
                'especie' => 'Cereza',
                'categoria' => 'Exportación',
                'envase' => 'Caja 5 kg',
                'csg' => '111',
                'predio' => 'Predio',
                'cuartel' => 'Cuartel',
                'fecha_embalaje' => '2026-09-20',
                'cantidad_cajas' => $cantidad,
            ],
        ]);
    }
}
