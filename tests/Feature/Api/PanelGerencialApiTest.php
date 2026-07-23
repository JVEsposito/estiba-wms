<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoFolioProcesoPrefrio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\ClienteMaterial;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Posicion;
use App\Models\PosicionTunelPrefrio;
use App\Models\ProcesoPrefrio;
use App\Models\ProcesoPrefrioFolio;
use App\Models\TunelPrefrio;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PanelGerencialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrega_una_instantanea_gerencial_con_capacidad_stock_y_disponibilidad(): void
    {
        $gerencia = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $this->crearProductoUbicado('PROD-001');
        Folio::create([
            'numero_folio' => 'PROD-BLOQUEADO',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Bloqueado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        $this->crearStockMaterial($administrador);
        $folioPrefrio = Folio::create([
            'numero_folio' => 'PROD-PREFRIO',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::PendientePrefrio,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        $this->crearTunel($administrador, $folioPrefrio);

        $respuesta = $this->actingAs($gerencia, 'sanctum')
            ->getJson('/api/gerencia/resumen')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.actualizacion_segundos', 30)
            ->assertJsonPath('data.camaras.resumen.operativas', 2)
            ->assertJsonPath('data.camaras.resumen.ocupadas', 1)
            ->assertJsonPath('data.camaras.resumen.disponibles', 1)
            ->assertJsonPath('data.camaras.resumen.ocupacion_porcentaje', 50)
            ->assertJsonPath('data.productos.total_activos', 3)
            ->assertJsonPath('data.productos.disponibles_despacho', 1)
            ->assertJsonPath('data.productos.pendientes_prefrio', 1)
            ->assertJsonPath('data.productos.bloqueados', 1)
            ->assertJsonPath('data.materiales.items_con_stock', 1)
            ->assertJsonPath('data.materiales.folios_con_stock', 1)
            ->assertJsonPath('data.materiales.unidades_medida.0.unidad_medida', 'unidad')
            ->assertJsonPath('data.materiales.unidades_medida.0.cantidad_actual', 125)
            ->assertJsonPath('data.materiales.unidades_medida.0.cantidad_reservada', 25)
            ->assertJsonPath('data.materiales.unidades_medida.0.cantidad_disponible', 100)
            ->assertJsonPath('data.materiales.unidades_medida.0.items.0.cliente.codigo', 'GENERAL')
            ->assertJsonPath('data.materiales.unidades_medida.0.items.0.temporada.activa', true)
            ->assertJsonPath('data.prefrio.tuneles_operativos', 1)
            ->assertJsonPath('data.prefrio.capacidad', 2)
            ->assertJsonPath('data.prefrio.ocupadas', 1)
            ->assertJsonPath('data.prefrio.disponibles', 1)
            ->assertJsonPath('data.prefrio.tuneles.0.proceso_activo.estado', EstadoProcesoPrefrio::Cargando->value)
            ->assertJsonPath('data.romana.en_bascula_ingreso', 0)
            ->assertJsonPath('data.romana.pendientes_destare', 0)
            ->assertJsonPath('data.romana.cerradas_hoy', 0)
            ->assertJsonPath('data.romana.peso_neto_hoy', 0)
            ->assertJsonCount(7, 'data.romana.tendencia_diaria');

        $this->assertNotNull($respuesta->json('data.generado_at'));
    }

    public function test_restringe_el_panel_a_perfiles_gerenciales_de_solo_consulta(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);

        $this->getJson('/api/gerencia/resumen')->assertUnauthorized();
        $this->actingAs($operador, 'sanctum')
            ->getJson('/api/gerencia/resumen')
            ->assertForbidden();
    }

    public function test_el_acceso_de_oficina_expone_la_capacidad_gerencial(): void
    {
        $gerencia = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'email' => 'gerencia@estiba.local',
            'password' => 'password123',
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $gerencia->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_consultar_panel_gerencial', true)
            ->assertJsonPath('usuario.capacidades.puede_consultar_panel_gerencial', true);
    }

    private function crearProductoUbicado(string $numeroFolio): Folio
    {
        $operador = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet de prueba',
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-GE-01',
            'nombre' => 'Cámara gerencial',
            'contenido' => ContenidoCamara::Productos,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 2,
            'nivel' => 1,
            'etiqueta' => 'B01-P02-N1',
        ]);
        $sesion = app(ServicioSesionEstiba::class)->abrir($camara, $operador, $dispositivo);
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

    private function crearStockMaterial(User $administrador): void
    {
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $item = ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => 'MAT-GE-01',
            'nombre' => 'Caja de prueba',
            'categoria' => 'Envases',
            'unidad_medida' => 'unidad',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $folio = Folio::create([
            'numero_folio' => 'MAT-FOLIO-001',
            'tipo_bulto' => TipoBulto::Material,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'cantidad_inicial' => 125,
            'cantidad_actual' => 125,
            'cantidad_reservada' => 25,
            'unidad_medida' => 'unidad',
        ]);
    }

    private function crearTunel(User $administrador, Folio $folio): void
    {
        $tunel = TunelPrefrio::create([
            'codigo' => 'TUN-GE-01',
            'nombre' => 'Túnel gerencial',
            'capacidad_posiciones' => 2,
            'creado_por_user_id' => $administrador->id,
        ]);

        foreach (range(1, 2) as $numero) {
            PosicionTunelPrefrio::create([
                'tunel_prefrio_id' => $tunel->id,
                'numero' => $numero,
                'etiqueta' => "P{$numero}",
                'activa' => true,
            ]);
        }

        $proceso = ProcesoPrefrio::create([
            'codigo' => 'PF-GE-01',
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'panel-gerencial'),
            'tunel_prefrio_id' => $tunel->id,
            'estado' => EstadoProcesoPrefrio::Cargando,
            'setpoint' => -1.5,
            'creado_por_user_id' => $administrador->id,
        ]);

        ProcesoPrefrioFolio::create([
            'proceso_prefrio_id' => $proceso->id,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $tunel->posiciones()->orderBy('numero')->value('id'),
            'estado' => EstadoFolioProcesoPrefrio::Cargado,
            'cargado_at' => now(),
            'cargado_por_user_id' => $administrador->id,
        ]);
    }
}
