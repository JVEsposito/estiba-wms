<?php

namespace Tests\Feature\Api;

use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Estiba\ServicioPlanesOperacionales;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanesOperacionalesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_plan_ordenado_solo_para_pallets_completos(): void
    {
        $contexto = $this->crearContexto();
        $servicio = app(ServicioPlanesOperacionales::class);

        $plan = $servicio->crear(
            temporada: $contexto['temporada'],
            tipo: TipoPlanOperacional::ReordenamientoCamara,
            titulo: 'Concentrar pallets de Exportadora Norte',
            creadoPor: $contexto['supervisor'],
            tareas: [
                [
                    'folio_id' => $contexto['folios'][0]->id,
                    'tipo_movimiento' => TipoMovimiento::Reubicacion,
                    'camara_origen_id' => $contexto['camara']->id,
                    'posicion_origen_id' => $contexto['posiciones'][0]->id,
                    'camara_destino_id' => $contexto['camara']->id,
                    'posicion_destino_id' => $contexto['posiciones'][1]->id,
                    'instruccion' => 'Mover primero para liberar el acceso.',
                ],
                [
                    'folio_id' => $contexto['folios'][1]->id,
                    'tipo_movimiento' => TipoMovimiento::Retiro,
                    'prioridad' => PrioridadOperacional::Urgente,
                    'camara_origen_id' => $contexto['camara']->id,
                    'posicion_origen_id' => $contexto['posiciones'][2]->id,
                ],
            ],
            prioridad: PrioridadOperacional::Alta,
            motivo: 'Preparación operacional controlada.',
            referenciaTipo: 'operacion_manual',
            referenciaId: (string) Str::uuid(),
        );

        $this->assertSame('programado', $plan->estado->value);
        $this->assertSame([1, 2], $plan->tareas->pluck('secuencia')->all());
        $this->assertSame('alta', $plan->tareas[0]->prioridad->value);
        $this->assertSame('urgente', $plan->tareas[1]->prioridad->value);
        $this->assertDatabaseHas('planes_operacionales', [
            'id' => $plan->id,
            'temporada_id' => $contexto['temporada']->id,
            'tipo' => 'reordenamiento_camara',
        ]);

        $saldo = Folio::create([
            'temporada_id' => $contexto['temporada']->id,
            'numero_folio' => 'SALDO-001',
            'tipo_bulto' => TipoBulto::Saldo,
            'fecha_ingreso' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('solo admiten pallets completos');
        $servicio->crear(
            temporada: $contexto['temporada'],
            tipo: TipoPlanOperacional::AlmacenamientoPallet,
            titulo: 'Intento con saldo',
            creadoPor: $contexto['supervisor'],
            tareas: [[
                'folio_id' => $saldo->id,
                'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
                'camara_destino_id' => $contexto['camara']->id,
                'posicion_destino_id' => $contexto['posiciones'][3]->id,
            ]],
        );
    }

    public function test_bandeja_permite_asumir_y_liberar_una_tarea_de_forma_exclusiva(): void
    {
        $contexto = $this->crearContexto();
        $plan = $this->crearPlan($contexto);
        $tarea = $plan->tareas->firstOrFail();

        $this->conToken($contexto['token'])
            ->getJson('/api/tareas-movimiento')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $tarea->id)
            ->assertJsonPath('data.0.folio.numero_folio', 'PALLET-001')
            ->assertJsonPath('data.0.destino.camara.nombre', 'Cámara tránsito');

        $this->conToken($contexto['token'])
            ->postJson("/api/tareas-movimiento/{$tarea->id}/asumir")
            ->assertOk()
            ->assertJsonPath('data.estado', 'asumida')
            ->assertJsonPath('data.responsable.id', $contexto['camarero']->id)
            ->assertJsonPath('data.dispositivo.id', $contexto['dispositivo']->id);

        $this->assertDatabaseHas('planes_operacionales', [
            'id' => $plan->id,
            'estado' => 'en_ejecucion',
            'iniciado_por_user_id' => $contexto['camarero']->id,
        ]);

        $this->conToken($contexto['token'])
            ->postJson("/api/tareas-movimiento/{$tarea->id}/asumir")
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->conToken($contexto['tokenOtro'])
            ->postJson("/api/tareas-movimiento/{$tarea->id}/asumir")
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->conToken($contexto['tokenOtro'])
            ->postJson("/api/tareas-movimiento/{$tarea->id}/liberar")
            ->assertConflict();

        $this->conToken($contexto['token'])
            ->postJson("/api/tareas-movimiento/{$tarea->id}/liberar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.responsable', null)
            ->assertJsonPath('data.version', 3);
    }

    public function test_las_consultas_se_limitan_a_la_temporada_activa_y_al_rol_operacional(): void
    {
        $contexto = $this->crearContexto();
        $plan = $this->crearPlan($contexto);
        $consulta = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'activo' => true,
        ]);
        $tokenConsulta = $consulta->createToken('consulta', ['oficina'])->plainTextToken;

        $this->conToken($contexto['token'])
            ->getJson('/api/planes-operacionales')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $plan->id)
            ->assertJsonPath('data.0.total_tareas', 1);

        $this->conToken($tokenConsulta)
            ->getJson('/api/planes-operacionales')
            ->assertForbidden();

        $contexto['temporada']->update(['activa' => false]);
        Temporada::create([
            'codigo' => 'TEMP-2027',
            'nombre' => 'Temporada 2027',
            'fecha_inicio' => '2027-01-01',
            'activa' => true,
        ]);

        $this->conToken($contexto['token'])
            ->getJson('/api/planes-operacionales')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->conToken($contexto['token'])
            ->getJson("/api/planes-operacionales/{$plan->id}")
            ->assertNotFound();
    }

    public function test_un_conflicto_de_asignacion_no_es_una_regla_generica(): void
    {
        $this->assertTrue(is_subclass_of(ConflictoOperacion::class, DomainException::class));
        $this->assertFalse(config('planificador.generacion_automatica'));
    }

    /** @param array<string, mixed> $contexto */
    private function crearPlan(array $contexto): PlanOperacional
    {
        return app(ServicioPlanesOperacionales::class)->crear(
            temporada: $contexto['temporada'],
            tipo: TipoPlanOperacional::AlmacenamientoPallet,
            titulo: 'Almacenar pallet completo',
            creadoPor: $contexto['supervisor'],
            tareas: [[
                'folio_id' => $contexto['folios'][0]->id,
                'tipo_movimiento' => TipoMovimiento::UbicacionInicial,
                'camara_destino_id' => $contexto['camara']->id,
                'posicion_destino_id' => $contexto['posiciones'][0]->id,
            ]],
        );
    }

    /** @return array<string, mixed> */
    private function crearContexto(): array
    {
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-2026',
            'nombre' => 'Temporada 2026',
            'fecha_inicio' => '2026-01-01',
            'activa' => true,
        ]);
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $otroCamarero = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet 01',
        ]);
        $otroDispositivo = Dispositivo::create([
            'codigo' => 'TABLET-02',
            'nombre' => 'Tablet 02',
        ]);
        $token = $camarero
            ->crearTokenParaDispositivo($dispositivo, 'tablet-01')
            ->plainTextToken;
        $tokenOtro = $otroCamarero
            ->crearTokenParaDispositivo($otroDispositivo, 'tablet-02')
            ->plainTextToken;
        $camara = Camara::create([
            'codigo' => 'CAM-01',
            'nombre' => 'Cámara tránsito',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 4,
            'cantidad_niveles' => 1,
        ]);
        $posiciones = [];

        for ($indice = 1; $indice <= 4; $indice++) {
            $posiciones[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $indice,
                'nivel' => 1,
                'etiqueta' => sprintf('B01-P%02d-N1', $indice),
            ]);
        }

        $folios = [1, 2];
        $folios = array_map(fn (int $indice): Folio => Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => sprintf('PALLET-%03d', $indice),
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]), $folios);

        return compact(
            'temporada',
            'supervisor',
            'camarero',
            'otroCamarero',
            'dispositivo',
            'otroDispositivo',
            'token',
            'tokenOtro',
            'camara',
            'posiciones',
            'folios',
        );
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
