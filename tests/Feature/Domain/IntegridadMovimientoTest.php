<?php

namespace Tests\Feature\Domain;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\EstadoPosicion;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Models\User;
use App\Services\Estiba\ServicioSesionEstiba;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegridadMovimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_movimiento_no_puede_modificarse(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $movimiento = $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $camara,
            $posicion,
            $sesion,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('inalterables');

        $movimiento->update(['motivo' => 'Cambio indebido']);
    }

    public function test_un_movimiento_no_puede_eliminarse(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $movimiento = $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $camara,
            $posicion,
            $sesion,
        );

        $this->expectException(DomainException::class);
        $movimiento->delete();
    }

    public function test_una_posicion_de_destino_debe_pertenecer_a_la_camara_declarada(): void
    {
        [$usuario, $dispositivo, $camara, $posicion] = $this->crearContexto();
        $otraCamara = Camara::create(['codigo' => 'CAM-02', 'nombre' => 'Cámara 02']);
        $otraSesion = app(ServicioSesionEstiba::class)
            ->abrir($otraCamara, $usuario, $dispositivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no pertenece a la cámara');

        $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $otraCamara,
            $posicion,
            $otraSesion,
        );
    }

    public function test_una_posicion_bloqueada_no_puede_ser_destino(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $posicion->update(['estado' => EstadoPosicion::Bloqueada]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('posición de destino no se encuentra activa');

        $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $camara,
            $posicion,
            $sesion,
        );
    }

    public function test_las_versiones_de_un_movimiento_se_convierten_a_enteros(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $movimiento = $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $camara,
            $posicion,
            $sesion,
            10,
            11,
        );

        $this->assertSame(10, $movimiento->version_destino_anterior);
        $this->assertSame(11, $movimiento->version_destino_resultante);
        $this->assertSame(0, $sesion->version_inicial);
    }

    public function test_un_movimiento_rechaza_versiones_incompletas(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('incremento unitario');

        $this->crearMovimientoInicial(
            $usuario,
            $dispositivo,
            $camara,
            $posicion,
            $sesion,
            0,
            2,
        );
    }

    public function test_la_operacion_debe_pertenecer_al_mismo_usuario_y_dispositivo(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $otroUsuario = User::factory()->create(['rol' => RolUsuario::Operador]);
        $folio = $this->crearFolio();
        $operacion = $this->crearOperacion($otroUsuario, $dispositivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no corresponde al movimiento');

        Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'version_destino_anterior' => 0,
            'version_destino_resultante' => 1,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }

    public function test_un_folio_bloqueado_no_puede_moverse(): void
    {
        [$usuario, $dispositivo, $camara, $posicion, $sesion] = $this->crearContexto();
        $folio = $this->crearFolio();
        $folio->update(['estado_operacional' => EstadoOperacionalFolio::Bloqueado]);
        $operacion = $this->crearOperacion($usuario, $dispositivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no se encuentra disponible');

        Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'version_destino_anterior' => 0,
            'version_destino_resultante' => 1,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }

    private function crearContexto(): array
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::Operador]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet 01',
        ]);
        $camara = Camara::create(['codigo' => 'CAM-01', 'nombre' => 'Cámara 01']);
        $posicion = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
        ]);
        $sesion = app(ServicioSesionEstiba::class)
            ->abrir($camara, $usuario, $dispositivo);

        return [$usuario, $dispositivo, $camara, $posicion, $sesion];
    }

    private function crearMovimientoInicial(
        User $usuario,
        Dispositivo $dispositivo,
        Camara $camara,
        Posicion $posicion,
        SesionEstiba $sesion,
        ?int $versionAnterior = 0,
        ?int $versionResultante = 1,
    ): Movimiento {
        $folio = $this->crearFolio();
        $operacion = $this->crearOperacion($usuario, $dispositivo);

        return Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'version_destino_anterior' => $versionAnterior,
            'version_destino_resultante' => $versionResultante,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }

    private function crearFolio(): Folio
    {
        return Folio::create([
            'numero_folio' => (string) Str::uuid(),
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
    }

    private function crearOperacion(
        User $usuario,
        Dispositivo $dispositivo,
    ): OperacionSincronizacion {
        return OperacionSincronizacion::create([
            'id' => (string) Str::uuid(),
            'user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
            'tipo' => TipoMovimiento::UbicacionInicial->value,
            'estado' => EstadoOperacionSincronizacion::Aceptada,
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'payload' => [],
            'generada_dispositivo_at' => now(),
            'recibida_servidor_at' => now(),
        ]);
    }
}
