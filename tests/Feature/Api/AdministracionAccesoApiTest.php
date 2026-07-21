<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\CategoriaValidacion;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Temporada;
use App\Models\TemporadaMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministracionAccesoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_crea_usuarios_y_tablets_autorizadas(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => '  Camila Operadora  ',
                'email' => '  CAMILA@EMPRESA.CL  ',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Temporal2026',
                'password_confirmation' => 'Temporal2026',
            ])
            ->assertCreated()
            ->assertJsonPath('usuario.nombre', 'Camila Operadora')
            ->assertJsonPath('usuario.email', 'camila@empresa.cl')
            ->assertJsonPath('usuario.rol', RolUsuario::CamareroFrio->value)
            ->assertJsonPath('usuario.activo', true);

        $usuario = User::query()->where('email', 'camila@empresa.cl')->firstOrFail();
        $this->assertTrue(Hash::check('Temporal2026', $usuario->password));

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => '  tablet-02  ',
                'nombre' => '  Tablet cámara norte  ',
            ])
            ->assertCreated()
            ->assertJsonPath('dispositivo.codigo', 'TABLET-02')
            ->assertJsonPath('dispositivo.nombre', 'Tablet cámara norte')
            ->assertJsonPath('dispositivo.plataforma', 'android')
            ->assertJsonPath('dispositivo.activo', true);

        $this->assertDatabaseHas('dispositivos', [
            'codigo' => 'TABLET-02',
            'nombre' => 'Tablet cámara norte',
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/accesos')
            ->assertOk()
            ->assertJsonCount(2, 'usuarios')
            ->assertJsonCount(1, 'dispositivos');
    }

    public function test_accesos_es_el_unico_dueno_de_la_temporada_transversal(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $temporada = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/temporadas', [
                'codigo' => ' 2026-2027 ',
                'nombre' => ' Temporada cerezas 2026-2027 ',
                'fecha_inicio' => '2026-10-01',
                'fecha_fin' => '2027-02-28',
                'activa' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', '2026-2027')
            ->assertJsonPath('data.activa', true)
            ->json('data');

        $this->assertNotNull($temporada['configuracion_material_id']);
        $this->assertDatabaseHas('temporadas_materiales', [
            'id' => $temporada['configuracion_material_id'],
            'temporada_id' => $temporada['id'],
            'activa' => true,
        ]);

        $nuevaId = $this->postJson('/api/administracion/temporadas', [
            'codigo' => '2027-2028',
            'nombre' => 'Temporada cerezas 2027-2028',
            'activa' => false,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/administracion/temporadas/{$nuevaId}/activar")
            ->assertOk()
            ->assertJsonPath('data.activa', true);

        $this->assertFalse(Temporada::query()->findOrFail($temporada['id'])->activa);
        $this->assertTrue(TemporadaMaterial::query()->where('temporada_id', $nuevaId)->firstOrFail()->activa);
        $this->getJson('/api/administracion/temporadas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nuevaId);

        $this->postJson('/api/administracion/validacion/temporadas', [])->assertNotFound();
        $this->postJson('/api/administracion/materiales/temporadas', [])->assertStatus(405);
    }

    public function test_un_usuario_no_administrador_no_puede_gestionar_accesos(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/administracion/accesos')
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Usuario no autorizado',
                'email' => 'sin-permiso@empresa.cl',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Temporal2026',
                'password_confirmation' => 'Temporal2026',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'TABLET-99',
                'nombre' => 'Tablet no autorizada',
            ])
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/administracion/temporadas', [
                'codigo' => '2026-2027',
                'nombre' => 'Temporada no autorizada',
            ])
            ->assertForbidden();

        $temporada = Temporada::query()->firstOrFail();
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/administracion/temporadas/{$temporada->id}/migrar", [])
            ->assertForbidden();
    }

    public function test_administrador_migra_catalogos_e_inventario_y_activa_el_destino_global(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $origen = Temporada::query()->where('activa', true)->firstOrFail();
        $configuracionOrigen = $origen->configuracionMaterial()->firstOrFail();
        $clienteOrigen = $configuracionOrigen->clientes()->firstOrFail();
        $itemOrigen = ItemMaterial::create([
            'cliente_material_id' => $clienteOrigen->id,
            'codigo' => 'FILM-MIGRABLE',
            'nombre' => 'Film migrable',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        CategoriaValidacion::create([
            'temporada_id' => $origen->id,
            'nombre' => 'Exportación',
            'activo' => true,
        ]);
        $folio = Folio::create([
            'temporada_id' => $origen->id,
            'numero_folio' => 'MAT-MIG-0001',
            'tipo_bulto' => TipoBulto::Material,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $itemOrigen->id,
            'cantidad_inicial' => 12.5,
            'cantidad_actual' => 10.5,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'rollos',
        ]);

        $destino = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/temporadas', [
                'codigo' => '2027-2028',
                'nombre' => 'Temporada 2027-2028',
                'activa' => false,
            ])
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/administracion/temporadas/{$destino['id']}/migrar", [
            'temporada_origen_id' => $origen->id,
            'copiar_catalogo_validacion' => true,
            'copiar_catalogo_materiales' => true,
            'migrar_inventario_materiales' => true,
            'activar_destino' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.destino.activa', true)
            ->assertJsonPath('data.resumen.validacion.categorias', 1)
            ->assertJsonPath('data.resumen.materiales.items', 1)
            ->assertJsonPath('data.resumen.inventario.folios', 1)
            ->assertJsonPath('data.resumen.inventario.cantidad_total', 10.5);

        $configuracionDestino = TemporadaMaterial::query()
            ->where('temporada_id', $destino['id'])
            ->firstOrFail();
        $itemDestino = ItemMaterial::query()
            ->where('codigo', 'FILM-MIGRABLE')
            ->whereHas('cliente', fn ($consulta) => $consulta
                ->where('temporada_material_id', $configuracionDestino->id))
            ->firstOrFail();

        $this->assertSame($itemDestino->id, $folio->material()->firstOrFail()->item_material_id);
        $this->assertSame($destino['id'], $folio->refresh()->temporada_id);
        $this->assertDatabaseHas('categorias_validacion', [
            'temporada_id' => $destino['id'],
            'nombre' => 'Exportación',
        ]);
        $this->assertDatabaseHas('migraciones_temporadas_folios', [
            'folio_id' => $folio->id,
            'item_material_origen_id' => $itemOrigen->id,
            'item_material_destino_id' => $itemDestino->id,
        ]);
        $this->assertFalse($origen->refresh()->activa);
        $this->assertTrue(Temporada::query()->findOrFail($destino['id'])->activa);
    }

    public function test_valida_duplicados_formato_y_contrasena(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
            'email' => 'existente@empresa.cl',
        ]);
        Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet existente',
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Duplicado',
                'email' => 'EXISTENTE@EMPRESA.CL',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'solo-letras',
                'password_confirmation' => 'no-coincide',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'tablet-01',
                'nombre' => 'Duplicada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/dispositivos', [
                'codigo' => 'tablet con espacios',
                'nombre' => 'Formato inválido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    public function test_informa_claramente_una_contrasena_demasiado_corta(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/usuarios', [
                'nombre' => 'Camarero de prueba',
                'email' => 'camarero@empresa.cl',
                'rol' => RolUsuario::CamareroFrio->value,
                'password' => 'Abc12',
                'password_confirmation' => 'Abc12',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                'La contraseña debe tener al menos 10 caracteres.',
            );
    }

    public function test_el_acceso_de_oficina_informa_el_permiso_administrativo(): void
    {
        User::factory()->create([
            'email' => 'admin@empresa.cl',
            'password' => 'password',
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => 'admin@empresa.cl',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_administrar_accesos', true)
            ->assertJsonPath('usuario.puede_gestionar_andenes', true)
            ->assertJsonPath('usuario.capacidades.puede_gestionar_andenes', true);
    }
}
