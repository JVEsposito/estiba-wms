<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoFolioProcesoPrefrio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\ConexionExistencia;
use App\Models\Folio;
use App\Models\PosicionTunelPrefrio;
use App\Models\ProcesoPrefrio;
use App\Models\ProcesoPrefrioFolio;
use App\Models\TunelPrefrio;
use App\Models\User;
use App\Services\Existencias\ServicioExistencias;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExistenciasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_ve_las_tres_existencias_y_descarga_un_corte_xlsx(): void
    {
        [, $token] = $this->acceso(RolUsuario::Administrador);

        $this->withToken($token)
            ->getJson('/api/existencias')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['tipo' => 'producto-terminado'])
            ->assertJsonFragment(['tipo' => 'materiales'])
            ->assertJsonFragment(['tipo' => 'materia-prima']);

        $respuesta = $this->withToken($token)
            ->get('/api/existencias/producto-terminado/corte');

        $respuesta
            ->assertOk()
            ->assertDownload()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $this->assertStringContainsString(
            'Existencia_Producto_Terminado_',
            (string) $respuesta->headers->get('content-disposition'),
        );
    }

    public function test_producto_aprobado_en_prefrio_queda_pendiente_de_ubicacion_en_existencias(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $temporada = app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'TEMP-EX-PF',
            'nombre' => 'Temporada existencias Prefrío',
            'activa' => true,
        ], usuarioId: $administrador->id);
        $tunel = TunelPrefrio::create([
            'codigo' => 'TUN-EX-01',
            'nombre' => 'Túnel existencias',
            'capacidad_posiciones' => 2,
            'setpoint_habitual' => -1.5,
            'creado_por_user_id' => $administrador->id,
        ]);
        $posicion = PosicionTunelPrefrio::create([
            'tunel_prefrio_id' => $tunel->id,
            'numero' => 1,
            'etiqueta' => 'TUN-EX-01-P01',
            'activa' => true,
        ]);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => 'PAL-EX-PF-001',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::PendientePrefrio,
            'condicion_termica' => CondicionTermicaFolio::EnProceso,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::NoHabilitado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        $proceso = ProcesoPrefrio::create([
            'temporada_id' => $temporada->id,
            'codigo' => 'PF-EX-000001',
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'proceso-existencias'),
            'tunel_prefrio_id' => $tunel->id,
            'estado' => EstadoProcesoPrefrio::EnProceso,
            'setpoint' => -1.5,
            'version' => 3,
            'creado_por_user_id' => $administrador->id,
        ]);
        $asignacion = ProcesoPrefrioFolio::create([
            'proceso_prefrio_id' => $proceso->id,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'estado' => EstadoFolioProcesoPrefrio::EnProceso,
            'cargado_at' => now(),
            'cargado_por_user_id' => $administrador->id,
        ]);

        $filas = app(ServicioExistencias::class)
            ->filas(ServicioExistencias::PRODUCTO_TERMINADO);
        $this->assertInstanceOf(LazyCollection::class, $filas);
        $enPrefrio = $filas->firstWhere('folio', $folio->numero_folio);

        $this->assertSame('En Prefrío', $enPrefrio['etapa_actual']);
        $this->assertSame('TUN-EX-01 · PF-EX-000001', $enPrefrio['tunel_prefrio']);

        $proceso->update([
            'estado' => EstadoProcesoPrefrio::Aprobado,
            'finalizado_por_user_id' => $administrador->id,
            'finalizado_at' => now(),
        ]);
        $asignacion->update(['estado' => EstadoFolioProcesoPrefrio::Aprobado]);
        $folio->update([
            'condicion_termica' => CondicionTermicaFolio::PrefrioAprobado,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::Habilitado,
        ]);

        $pendiente = app(ServicioExistencias::class)
            ->filas(ServicioExistencias::PRODUCTO_TERMINADO)
            ->firstWhere('folio', $folio->numero_folio);

        $this->assertSame('Pendiente de ubicación', $pendiente['estado_operacional']);
        $this->assertSame('Pendiente de ubicación', $pendiente['etapa_actual']);
        $this->assertNull($pendiente['tunel_prefrio']);
        $this->assertNull($pendiente['camara']);
        $this->assertNull($pendiente['posicion']);
    }

    public function test_conexion_excel_es_revocable_y_deja_de_actualizarse(): void
    {
        [, $tokenOficina] = $this->acceso(RolUsuario::Administrador);

        $respuesta = $this->withToken($tokenOficina)
            ->post('/api/existencias/materiales/conexion-excel');

        $respuesta
            ->assertCreated()
            ->assertHeader('content-type', 'application/x-msquery; charset=UTF-8');
        $this->assertStringContainsString("WEB\r\n1\r\n", $respuesta->getContent());
        $this->assertMatchesRegularExpression('/token=([A-Za-z0-9]+)/', $respuesta->getContent());
        preg_match('/token=([A-Za-z0-9]+)/', $respuesta->getContent(), $coincidencias);
        $tokenConsulta = $coincidencias[1];
        $conexion = ConexionExistencia::query()->firstOrFail();

        $respuestaConsulta = $this->get('/api/existencias/materiales/consulta?token='.$tokenConsulta)
            ->assertOk()
            ->assertStreamed();
        $contenidoConsulta = $respuestaConsulta->streamedContent();
        $this->assertStringContainsString('Existencia de materiales', $contenidoConsulta);
        $this->assertStringContainsString('Cantidad disponible en almacén', $contenidoConsulta);
        $this->assertStringContainsString('Centro de costo', $contenidoConsulta);

        $this->withToken($tokenOficina)
            ->postJson("/api/existencias/conexiones/{$conexion->id}/revocar")
            ->assertOk()
            ->assertJsonPath('data.vigente', false);

        $this->get('/api/existencias/materiales/consulta?token='.$tokenConsulta)
            ->assertGone();
    }

    public function test_limita_los_cortes_xlsx_por_usuario(): void
    {
        [, $token] = $this->acceso(RolUsuario::Administrador);

        for ($intento = 1; $intento <= 3; $intento++) {
            $this->withToken($token)
                ->get('/api/existencias/materiales/corte')
                ->assertOk()
                ->assertDownload();
        }

        $this->withToken($token)
            ->get('/api/existencias/materiales/corte')
            ->assertTooManyRequests();
    }

    public function test_limita_la_actualizacion_excel_por_token_de_conexion(): void
    {
        [, $tokenOficina] = $this->acceso(RolUsuario::Administrador);
        $respuesta = $this->withToken($tokenOficina)
            ->post('/api/existencias/materiales/conexion-excel')
            ->assertCreated();
        preg_match('/token=([A-Za-z0-9]+)/', $respuesta->getContent(), $coincidencias);
        $tokenConsulta = $coincidencias[1];
        $url = '/api/existencias/materiales/consulta?token='.$tokenConsulta;

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->get($url)
                ->assertOk()
                ->assertStreamed()
                ->streamedContent();
        }

        $this->get($url)
            ->assertTooManyRequests();
    }

    public function test_supervisor_materiales_solo_recibe_existencia_de_materiales(): void
    {
        [, $token] = $this->acceso(RolUsuario::SupervisorMateriales);

        $this->withToken($token)
            ->get('/api/existencias/materia-prima/corte')
            ->assertForbidden();
        $this->withToken($token)
            ->get('/api/existencias/producto-terminado/corte')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/existencias')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'materiales');
    }

    public function test_usuario_de_consulta_accede_a_custodia_y_su_historial(): void
    {
        [, $token] = $this->acceso(RolUsuario::Consulta);

        $this->withToken($token)
            ->getJson('/api/materiales/almacenes')
            ->assertOk();
        $this->withToken($token)
            ->getJson('/api/materiales/almacenes/movimientos')
            ->assertOk();
    }

    public function test_oficina_de_existencias_esta_disponible(): void
    {
        $this->get('/oficina/existencias')
            ->assertOk()
            ->assertSee('Tres inventarios. Una fuente oficial.')
            ->assertSee('Excel conectado');

        $this->get('/oficina/materiales/almacenes')
            ->assertOk()
            ->assertSee('data-office-key="custodia"', false)
            ->assertSee('data-navigation-module="materiales.inventario"', false)
            ->assertDontSee('data-navigation-module="materiales.custodia"', false)
            ->assertSee("can('puede_consultar_kardex_materiales')", false)
            ->assertSee('const movementForm = event.currentTarget;', false)
            ->assertSee('submitButton.disabled = true;', false)
            ->assertDontSee('event.currentTarget.elements', false)
            ->assertSee('Existencia en centros de costo');
    }

    /** @return array{User, string} */
    private function acceso(RolUsuario $rol): array
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $token = $usuario->createToken('prueba-existencias', ['oficina'])->plainTextToken;

        return [$usuario, $token];
    }
}
