<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Posicion;
use App\Models\RegistroControlAmbiental;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperacionAhoraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requiere_un_perfil_gerencial_y_expone_solo_la_temporada_activa(): void
    {
        $temporada = $this->crearTemporada();
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);

        $this->getJson('/api/operacion-ahora')->assertUnauthorized();
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertForbidden();

        $this->actingAs($consulta, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.temporada.id', $temporada->id)
            ->assertJsonPath('data.jornada.zona_horaria', 'America/Santiago')
            ->assertJsonPath('data.jornada.turno', null)
            ->assertJsonPath('data.sincronizacion.estado', 'sin_actividad')
            ->assertJsonPath('data.sincronizacion.ultima_operacion', null)
            ->assertJsonPath('data.actualizacion_sugerida_segundos', 30)
            ->assertJsonCount(0, 'data.camaras');
    }

    public function test_consolida_ocupacion_control_ambiental_y_evidencia_de_sincronizacion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-07 12:00:00 UTC'));
        $this->crearTemporada();
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $camarero = User::factory()->create([
            'name' => 'Camarero operación actual',
            'rol' => RolUsuario::CamareroFrio,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TAB-AHORA-01',
            'nombre' => 'Tablet operación ahora',
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-AHORA-01',
            'nombre' => 'Cámara operación ahora',
            'contenido' => ContenidoCamara::Productos,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $sesion = app(ServicioSesionEstiba::class)->abrir(
            $camara,
            $camarero,
            $dispositivo,
        );
        app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: 'PAL-AHORA-001',
            tipoBulto: TipoBulto::Pallet,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $camarero,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
        );
        RegistroControlAmbiental::create([
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => str_repeat('a', 64),
            'camara_id' => $camara->id,
            'registrado_por_user_id' => $camarero->id,
            'dispositivo_id' => $dispositivo->id,
            'temperatura_inicio_c' => -1.20,
            'temperatura_medio_c' => -1.00,
            'temperatura_fondo_c' => -0.80,
            'capturado_at' => now()->subMinutes(30),
            'recibido_servidor_at' => now()->subMinutes(29),
        ]);
        RegistroControlAmbiental::create([
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => str_repeat('b', 64),
            'camara_id' => $camara->id,
            'registrado_por_user_id' => $camarero->id,
            'dispositivo_id' => $dispositivo->id,
            'temperatura_inicio_c' => 9,
            'temperatura_medio_c' => 9,
            'temperatura_fondo_c' => 9,
            'capturado_at' => now()->addMinutes(5),
            'recibido_servidor_at' => now(),
        ]);

        $this->actingAs($consulta, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertOk()
            ->assertJsonPath('data.sincronizacion.estado', 'aceptada')
            ->assertJsonPath('data.sincronizacion.operaciones_hoy.aceptada', 1)
            ->assertJsonPath('data.sincronizacion.ultima_operacion.dispositivo.codigo', 'TAB-AHORA-01')
            ->assertJsonPath('data.camaras.0.codigo', 'CAM-AHORA-01')
            ->assertJsonPath('data.camaras.0.ocupacion_porcentaje', 100)
            ->assertJsonPath('data.camaras.0.nivel_ocupacion', 'critica')
            ->assertJsonPath('data.camaras.0.alertas.0', 'ocupacion_critica')
            ->assertJsonPath('data.camaras.0.control_ambiental.estado', 'vigente')
            ->assertJsonPath('data.camaras.0.control_ambiental.temperaturas_c.promedio', -1);
    }

    private function crearTemporada(): Temporada
    {
        return Temporada::create([
            'codigo' => 'ACTUAL',
            'nombre' => 'Temporada actual',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2027-03-31',
            'activa' => true,
        ]);
    }
}
