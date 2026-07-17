<?php

namespace Tests\Feature\Api;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoProcesoPrefrio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PosicionTunelPrefrio;
use App\Models\ProcesoPrefrio;
use App\Models\ProcesoPrefrioFolio;
use App\Models\TunelPrefrio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoliosPrefrioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_recibe_solo_folios_elegibles_y_puede_buscar_por_escaneo(): void
    {
        [$operador, $dispositivo, $token] = $this->accesoPrefrio();
        $pendiente = $this->crearFolio(
            'PAL-PF-100',
            CondicionTermicaFolio::PendientePrefrio,
            HabilitacionAlmacenamientoFolio::NoHabilitado,
        );
        $reproceso = $this->crearFolio(
            'PAL-PF-200',
            CondicionTermicaFolio::RequiereReproceso,
            HabilitacionAlmacenamientoFolio::Retenido,
        );
        $habilitado = $this->crearFolio(
            'PAL-PF-300',
            CondicionTermicaFolio::PrefrioAprobado,
            HabilitacionAlmacenamientoFolio::Habilitado,
        );
        $enProceso = $this->crearFolio(
            'PAL-PF-400',
            CondicionTermicaFolio::PendientePrefrio,
            HabilitacionAlmacenamientoFolio::NoHabilitado,
        );
        $this->asignarAProcesoActivo($enProceso, $operador, $dispositivo);

        $this->withToken($token)
            ->getJson('/api/prefrio/folios-disponibles?limit=100')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.numero_folio', 'PAL-PF-100')
            ->assertJsonPath('data.1.numero_folio', 'PAL-PF-200');

        $this->withToken($token)
            ->getJson('/api/prefrio/folios-disponibles?folio=pf-200&limit=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reproceso->id)
            ->assertJsonPath('data.0.condicion_termica', 'requiere_reproceso');

        $this->assertNotSame($habilitado->id, $pendiente->id);
    }

    public function test_camarero_sin_capacidad_prefrio_no_consulta_catalogo(): void
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'PF-NO-AUTORIZADO',
            'nombre' => 'PDA no autorizada',
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo($dispositivo, 'test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/prefrio/folios-disponibles')
            ->assertForbidden();
    }

    /**
     * @return array{User, Dispositivo, string}
     */
    private function accesoPrefrio(): array
    {
        $usuario = User::factory()->create(['rol' => RolUsuario::OperadorPrefrio]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'PF-CATALOGO-01',
            'nombre' => 'PDA catálogo Prefrío',
            'plataforma' => 'android',
            'activo' => true,
        ]);
        $token = $usuario->crearTokenParaDispositivo($dispositivo, 'test')->plainTextToken;

        return [$usuario, $dispositivo, $token];
    }

    private function crearFolio(
        string $numero,
        CondicionTermicaFolio $condicion,
        HabilitacionAlmacenamientoFolio $habilitacion,
    ): Folio {
        return Folio::create([
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => $habilitacion === HabilitacionAlmacenamientoFolio::Habilitado
                ? EstadoOperacionalFolio::Disponible
                : EstadoOperacionalFolio::PendientePrefrio,
            'condicion_termica' => $condicion,
            'habilitacion_almacenamiento' => $habilitacion,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
    }

    private function asignarAProcesoActivo(
        Folio $folio,
        User $usuario,
        Dispositivo $dispositivo,
    ): void {
        $tunel = TunelPrefrio::create([
            'codigo' => 'TUN-CAT-01',
            'nombre' => 'Túnel catálogo',
            'capacidad_posiciones' => 1,
            'setpoint_habitual' => -1.5,
            'creado_por_user_id' => $usuario->id,
        ]);
        $posicion = PosicionTunelPrefrio::create([
            'tunel_prefrio_id' => $tunel->id,
            'numero' => 1,
            'etiqueta' => 'TUN-CAT-01-P01',
            'activa' => true,
        ]);
        $proceso = ProcesoPrefrio::create([
            'codigo' => 'PF-2026-999999',
            'operacion_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'catalogo'),
            'tunel_prefrio_id' => $tunel->id,
            'estado' => EstadoProcesoPrefrio::Cargando,
            'setpoint' => -1.5,
            'creado_por_user_id' => $usuario->id,
            'dispositivo_id' => $dispositivo->id,
        ]);
        ProcesoPrefrioFolio::create([
            'proceso_prefrio_id' => $proceso->id,
            'folio_id' => $folio->id,
            'posicion_tunel_prefrio_id' => $posicion->id,
            'estado' => 'cargado',
            'cargado_at' => now(),
            'cargado_por_user_id' => $usuario->id,
        ]);
    }
}
