<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoPosicion;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Posicion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionCamaraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_accede_desde_oficina_sin_permiso_para_configurar_camaras(): void
    {
        $usuario = User::factory()->create([
            'email' => 'supervisor@estiba.local',
            'password' => 'password',
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $respuesta = $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'supervisor_frio')
            ->assertJsonPath('usuario.puede_configurar_camaras', false)
            ->assertJsonPath('usuario.puede_administrar_camaras', false);

        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $usuario->id,
            'dispositivo_id' => null,
        ]);
    }

    public function test_camarero_accede_a_fruta_proceso_sin_permisos_administrativos(): void
    {
        $usuario = User::factory()->create([
            'email' => 'operador@estiba.local',
            'password' => 'password',
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('usuario.puede_consultar_fruta_proceso', true)
            ->assertJsonPath('usuario.puede_entregar_fruta_proceso', true)
            ->assertJsonPath('usuario.puede_corregir_entregas_fruta_proceso', false)
            ->assertJsonPath('usuario.puede_configurar_camaras', false)
            ->assertJsonPath('usuario.puede_administrar_camaras', false);
    }

    public function test_administrador_recibe_permiso_para_editar_camaras(): void
    {
        $usuario = User::factory()->create([
            'email' => 'administrador@estiba.local',
            'password' => 'password',
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('usuario.puede_configurar_camaras', true)
            ->assertJsonPath('usuario.puede_administrar_camaras', true);
    }

    public function test_administrador_crea_codigo_correlativo_y_posiciones_en_una_transaccion(): void
    {
        Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara existente']);
        DB::table('secuencias_documentos')
            ->where('clave', 'camaras')
            ->update(['ultimo_numero' => 1]);
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/configuracion/camaras/siguiente-codigo')
            ->assertOk()
            ->assertJsonPath('data.codigo', 'CAM-02');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $respuesta = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/configuracion/camaras', [
                'nombre' => 'Cámara de tránsito norte',
                'tipo' => 'transito',
                'bandas' => 2,
                'posiciones_por_banda' => 3,
                'niveles' => 2,
                'posiciones_fuera_servicio' => [
                    ['banda' => 2, 'posicion' => 3, 'nivel' => 2],
                ],
            ]);
        $consultas = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $consulta): string => strtolower($consulta));
        DB::disableQueryLog();
        DB::flushQueryLog();

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'CAM-02')
            ->assertJsonPath('data.dimensiones.bandas', 2)
            ->assertJsonPath('data.dimensiones.posiciones_por_banda', 3)
            ->assertJsonPath('data.dimensiones.niveles', 2)
            ->assertJsonPath('data.capacidad.total', 12)
            ->assertJsonPath('data.capacidad.activas', 11)
            ->assertJsonPath('data.capacidad.fuera_servicio', 1);

        $camara = Camara::query()->where('codigo', 'CAM-02')->firstOrFail();
        $this->assertSame($administrador->id, $camara->creado_por_user_id);
        $this->assertSame(12, $camara->posiciones()->count());
        $this->assertDatabaseHas('posiciones', [
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
            'estado' => EstadoPosicion::Activa->value,
        ]);
        $this->assertDatabaseHas('posiciones', [
            'camara_id' => $camara->id,
            'banda' => 2,
            'posicion' => 3,
            'nivel' => 2,
            'estado' => EstadoPosicion::FueraDeServicio->value,
        ]);
        $this->assertDatabaseHas('secuencias_documentos', [
            'clave' => 'camaras',
            'ultimo_numero' => 2,
        ]);
        $this->assertFalse(
            $consultas->contains(fn (string $consulta): bool => str_contains(
                $consulta,
                'camaras',
            ) && str_contains($consulta, 'order by')
                && str_contains($consulta, 'codigo')),
            'Crear una cámara no debe recorrer ni bloquear el catálogo de códigos.',
        );
    }

    public function test_supervisor_y_operador_no_pueden_configurar_camaras(): void
    {
        foreach ([RolUsuario::SupervisorFrio, RolUsuario::CamareroFrio] as $rol) {
            $usuario = User::factory()->create([
                'rol' => $rol,
                'activo' => true,
            ]);

            $this->actingAs($usuario, 'sanctum')
                ->postJson('/api/configuracion/camaras', [
                    'nombre' => 'Cámara bloqueada',
                    'tipo' => 'transito',
                    'bandas' => 1,
                    'posiciones_por_banda' => 1,
                    'niveles' => 1,
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, Posicion::query()->count());
    }

    public function test_administrador_no_puede_crear_planos_mayores_a_mil_posiciones(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/configuracion/camaras', [
                'nombre' => 'Cámara demasiado grande',
                'tipo' => 'almacenaje',
                'bandas' => 40,
                'posiciones_por_banda' => 40,
                'niveles' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bandas');
    }
}
