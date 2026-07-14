<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoPosicion;
use App\Enums\RolUsuario;
use App\Models\Camara;
use App\Models\Posicion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionCamaraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_accede_desde_oficina_sin_dispositivo_registrado(): void
    {
        $usuario = User::factory()->create([
            'email' => 'supervisor@estiba.local',
            'password' => 'password',
            'rol' => RolUsuario::Supervisor,
            'activo' => true,
        ]);

        $respuesta = $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'supervisor')
            ->assertJsonPath('usuario.puede_configurar_camaras', true);

        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $usuario->id,
            'dispositivo_id' => null,
        ]);
    }

    public function test_operador_no_puede_ingresar_a_los_modulos_de_oficina(): void
    {
        $usuario = User::factory()->create([
            'email' => 'operador@estiba.local',
            'password' => 'password',
            'rol' => RolUsuario::Operador,
            'activo' => true,
        ]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_crea_codigo_correlativo_y_posiciones_en_una_transaccion(): void
    {
        Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara existente']);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::Supervisor,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($supervisor, 'sanctum')
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
        $this->assertSame($supervisor->id, $camara->creado_por_user_id);
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
    }

    public function test_solo_supervisor_o_administrador_puede_configurar_camaras(): void
    {
        $operador = User::factory()->create([
            'rol' => RolUsuario::Operador,
            'activo' => true,
        ]);

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/configuracion/camaras', [
                'nombre' => 'Cámara bloqueada',
                'tipo' => 'transito',
                'bandas' => 1,
                'posiciones_por_banda' => 1,
                'niveles' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, Posicion::query()->count());
    }

    public function test_rechaza_planos_mayores_a_mil_posiciones(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::Supervisor,
            'activo' => true,
        ]);

        $this->actingAs($supervisor, 'sanctum')
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
