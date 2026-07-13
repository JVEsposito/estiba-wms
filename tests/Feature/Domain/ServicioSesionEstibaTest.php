<?php

namespace Tests\Feature\Domain;

use App\Enums\EstadoSesionEstiba;
use App\Enums\RolUsuario;
use App\Models\BloqueoCamara;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\SesionEstiba;
use App\Models\User;
use App\Services\Estiba\ServicioSesionEstiba;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicioSesionEstibaTest extends TestCase
{
    use RefreshDatabase;

    public function test_abrir_una_sesion_crea_el_bloqueo_de_forma_conjunta(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();

        $sesion = $this->servicio()->abrir($camara, $usuario, $dispositivo);

        $this->assertSame(EstadoSesionEstiba::Abierta, $sesion->estado);
        $this->assertSame(0, $sesion->version_inicial);
        $this->assertDatabaseHas('bloqueos_camara', [
            'camara_id' => $camara->id,
            'sesion_estiba_id' => $sesion->id,
        ]);
    }

    public function test_no_pueden_abrirse_dos_sesiones_sobre_la_misma_camara(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();
        $servicio = $this->servicio();
        $servicio->abrir($camara, $usuario, $dispositivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La cámara ya está siendo modificada');

        $servicio->abrir($camara, $usuario, $dispositivo);
    }

    public function test_una_sesion_abierta_sin_bloqueo_no_se_reemplaza_silenciosamente(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();
        SesionEstiba::create([
            'camara_id' => $camara->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'estado' => EstadoSesionEstiba::Abierta,
            'version_inicial' => 0,
            'iniciada_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya está siendo modificada');

        $this->servicio()->abrir($camara, $usuario, $dispositivo);
    }

    public function test_cerrar_una_sesion_libera_la_camara_y_permite_reabrirla(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();
        $servicio = $this->servicio();
        $primera = $servicio->abrir($camara, $usuario, $dispositivo);

        $cerrada = $servicio->cerrar($primera, $usuario, 'Fin de turno');
        $segunda = $servicio->abrir($camara, $usuario, $dispositivo);

        $this->assertSame(EstadoSesionEstiba::Cerrada, $cerrada->estado);
        $this->assertSame('Fin de turno', $cerrada->motivo_cierre);
        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertSame(1, BloqueoCamara::query()->count());
    }

    public function test_un_supervisor_puede_realizar_un_cierre_forzado(): void
    {
        [$operador, $dispositivo, $camara] = $this->crearContexto();
        $supervisor = User::factory()->create(['rol' => RolUsuario::Supervisor]);
        $servicio = $this->servicio();
        $sesion = $servicio->abrir($camara, $operador, $dispositivo);

        $cerrada = $servicio->cerrar($sesion, $supervisor, 'Sesión abandonada');

        $this->assertSame(EstadoSesionEstiba::CierreForzado, $cerrada->estado);
        $this->assertSame($supervisor->id, $cerrada->cierre_forzado_por_user_id);
        $this->assertFalse($cerrada->bloqueo()->exists());
    }

    public function test_un_operador_no_puede_cerrar_la_sesion_de_otro_usuario(): void
    {
        [$propietario, $dispositivo, $camara] = $this->crearContexto();
        $otroOperador = User::factory()->create(['rol' => RolUsuario::Operador]);
        $servicio = $this->servicio();
        $sesion = $servicio->abrir($camara, $propietario, $dispositivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no puede cerrar una sesión ajena');

        $servicio->cerrar($sesion, $otroOperador);
    }

    public function test_un_usuario_de_consulta_no_puede_abrir_sesiones(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();
        $usuario->update(['rol' => RolUsuario::Consulta]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no está autorizado');

        $this->servicio()->abrir($camara, $usuario, $dispositivo);
    }

    public function test_un_bloqueo_no_puede_apuntar_a_una_sesion_de_otra_camara(): void
    {
        [$usuario, $dispositivo, $camara] = $this->crearContexto();
        $otraCamara = Camara::create(['codigo' => 'CAM-02', 'nombre' => 'Cámara 02']);
        $sesion = SesionEstiba::create([
            'camara_id' => $camara->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'estado' => EstadoSesionEstiba::Abierta,
            'version_inicial' => 0,
            'iniciada_at' => now(),
        ]);

        $this->expectException(DomainException::class);

        BloqueoCamara::create([
            'camara_id' => $otraCamara->id,
            'sesion_estiba_id' => $sesion->id,
            'adquirido_at' => now(),
        ]);
    }

    private function crearContexto(): array
    {
        return [
            User::factory()->create(['rol' => RolUsuario::Operador]),
            Dispositivo::create(['codigo' => 'TABLET-01', 'nombre' => 'Tablet 01']),
            Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara 01']),
        ];
    }

    private function servicio(): ServicioSesionEstiba
    {
        return app(ServicioSesionEstiba::class);
    }
}
