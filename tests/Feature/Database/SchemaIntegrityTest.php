<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoOperacionSincronizacion;
use App\Enums\EstadoSesionEstiba;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Models\BloqueoCamara;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\OperacionSincronizacion;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Models\UbicacionActual;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_coordenadas_de_una_posicion_son_unicas_por_camara(): void
    {
        $camara = $this->crearCamara('CAM-01');

        $this->crearPosicion($camara, 'A', 1, 1);

        $this->expectException(QueryException::class);

        $this->crearPosicion($camara, 'A', 1, 1);
    }

    public function test_un_folio_no_puede_ocupar_dos_posiciones(): void
    {
        [$user, $dispositivo, $camara, $sesion] = $this->crearContexto();
        $folio = $this->crearFolio('FOLIO-001');
        $posicionUno = $this->crearPosicion($camara, 'A', 1, 1);
        $posicionDos = $this->crearPosicion($camara, 'A', 2, 1);

        $movimientoUno = $this->crearMovimientoInicial(
            $folio,
            $posicionUno,
            $sesion,
            $user,
            $dispositivo,
        );
        $movimientoDos = $this->crearMovimientoInicial(
            $folio,
            $posicionDos,
            $sesion,
            $user,
            $dispositivo,
        );

        UbicacionActual::create([
            'folio_id' => $folio->id,
            'posicion_id' => $posicionUno->id,
            'movimiento_id' => $movimientoUno->id,
            'ubicado_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        UbicacionActual::create([
            'folio_id' => $folio->id,
            'posicion_id' => $posicionDos->id,
            'movimiento_id' => $movimientoDos->id,
            'ubicado_at' => now(),
        ]);
    }

    public function test_una_posicion_no_puede_contener_dos_folios(): void
    {
        [$user, $dispositivo, $camara, $sesion] = $this->crearContexto();
        $posicion = $this->crearPosicion($camara, 'A', 1, 1);
        $folioUno = $this->crearFolio('FOLIO-001');
        $folioDos = $this->crearFolio('FOLIO-002');
        $movimientoUno = $this->crearMovimientoInicial(
            $folioUno,
            $posicion,
            $sesion,
            $user,
            $dispositivo,
        );
        $movimientoDos = $this->crearMovimientoInicial(
            $folioDos,
            $posicion,
            $sesion,
            $user,
            $dispositivo,
        );

        UbicacionActual::create([
            'folio_id' => $folioUno->id,
            'posicion_id' => $posicion->id,
            'movimiento_id' => $movimientoUno->id,
            'ubicado_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        UbicacionActual::create([
            'folio_id' => $folioDos->id,
            'posicion_id' => $posicion->id,
            'movimiento_id' => $movimientoDos->id,
            'ubicado_at' => now(),
        ]);
    }

    public function test_solo_puede_existir_un_bloqueo_activo_por_camara(): void
    {
        [$user, $dispositivo, $camara, $sesionUno] = $this->crearContexto();
        $sesionDos = SesionEstiba::create([
            'camara_id' => $camara->id,
            'user_id' => $user->id,
            'dispositivo_id' => $dispositivo->id,
            'estado' => EstadoSesionEstiba::Abierta,
            'version_inicial' => 0,
            'iniciada_at' => now(),
        ]);

        BloqueoCamara::create([
            'camara_id' => $camara->id,
            'sesion_estiba_id' => $sesionUno->id,
            'adquirido_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        BloqueoCamara::create([
            'camara_id' => $camara->id,
            'sesion_estiba_id' => $sesionDos->id,
            'adquirido_at' => now(),
        ]);
    }

    public function test_un_folio_con_movimientos_no_puede_eliminarse(): void
    {
        [$user, $dispositivo, $camara, $sesion] = $this->crearContexto();
        $folio = $this->crearFolio('FOLIO-001');
        $posicion = $this->crearPosicion($camara, 'A', 1, 1);

        $this->crearMovimientoInicial(
            $folio,
            $posicion,
            $sesion,
            $user,
            $dispositivo,
        );

        $this->expectException(QueryException::class);

        $folio->delete();
    }

    /**
     * @return array{User, Dispositivo, Camara, SesionEstiba}
     */
    private function crearContexto(): array
    {
        $user = User::factory()->create();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet de prueba',
        ]);
        $camara = $this->crearCamara('CAM-01');
        $sesion = SesionEstiba::create([
            'camara_id' => $camara->id,
            'user_id' => $user->id,
            'dispositivo_id' => $dispositivo->id,
            'estado' => EstadoSesionEstiba::Abierta,
            'version_inicial' => 0,
            'iniciada_at' => now(),
        ]);

        return [$user, $dispositivo, $camara, $sesion];
    }

    private function crearCamara(string $codigo): Camara
    {
        return Camara::create([
            'codigo' => $codigo,
            'nombre' => "Cámara {$codigo}",
            'tipo' => 'almacenaje',
        ]);
    }

    private function crearPosicion(
        Camara $camara,
        string $fila,
        int $profundidad,
        int $nivel,
    ): Posicion {
        return Posicion::create([
            'camara_id' => $camara->id,
            'fila' => $fila,
            'profundidad' => $profundidad,
            'nivel' => $nivel,
        ]);
    }

    private function crearFolio(string $numero): Folio
    {
        return Folio::create([
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
    }

    private function crearMovimientoInicial(
        Folio $folio,
        Posicion $posicion,
        SesionEstiba $sesion,
        User $user,
        Dispositivo $dispositivo,
    ): Movimiento {
        $operacion = OperacionSincronizacion::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'dispositivo_id' => $dispositivo->id,
            'tipo' => TipoMovimiento::UbicacionInicial->value,
            'estado' => EstadoOperacionSincronizacion::Aceptada,
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'payload' => ['folio' => $folio->numero_folio],
            'generada_dispositivo_at' => now(),
            'recibida_servidor_at' => now(),
            'procesada_at' => now(),
        ]);

        return Movimiento::create([
            'operacion_id' => $operacion->id,
            'folio_id' => $folio->id,
            'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
            'camara_destino_id' => $posicion->camara_id,
            'posicion_destino_id' => $posicion->id,
            'sesion_destino_id' => $sesion->id,
            'user_id' => $user->id,
            'dispositivo_id' => $dispositivo->id,
            'version_destino_anterior' => 0,
            'version_destino_resultante' => 1,
            'generado_dispositivo_at' => now(),
            'recibido_servidor_at' => now(),
        ]);
    }
}
