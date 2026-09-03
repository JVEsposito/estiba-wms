<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\PrioridadCarga;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Exceptions\ConflictoOperacion;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\ReservaCargaFolio;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use App\Services\Estiba\ServicioPlanesOperacionales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcentracionCargaRollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_que_ya_cumple_80_por_ciento_no_genera_movimientos(): void
    {
        $contexto = $this->crearContexto(total: 5, concentrados: 4, fuera: 1);

        $plan = app(ServicioPlanConcentracionCarga::class)->sincronizar(
            $contexto['carga'],
            $contexto['usuario'],
        );

        $this->assertNull($plan);
        $this->assertDatabaseMissing('planes_operacionales', [
            'referencia_tipo' => 'carga_concentracion',
            'referencia_id' => $contexto['carga']->id,
        ]);
        $this->assertDatabaseCount('tareas_movimiento', 0);
    }

    public function test_publica_solo_el_minimo_necesario_para_llegar_al_80_por_ciento(): void
    {
        $contexto = $this->crearContexto(total: 10, concentrados: 7, fuera: 3);

        $plan = app(ServicioPlanConcentracionCarga::class)->sincronizar(
            $contexto['carga'],
            $contexto['usuario'],
        );

        $this->assertNotNull($plan);
        $this->assertSame('concentracion_carga', $plan->tipo->value);
        $this->assertSame('rolling', $plan->contexto['planner_horizon']);
        $this->assertSame(70, $plan->contexto['porcentaje_actual']);
        $this->assertSame(80, $plan->contexto['umbral_porcentaje']);
        $this->assertSame($contexto['camaraObjetivo']->id, $plan->contexto['camara_objetivo_id']);
        $this->assertSame(1, $plan->tareas()->count());

        $tarea = $plan->tareas()->sole();
        $this->assertSame('concentrar_carga', $tarea->contexto['tipo_decision']);
        $this->assertSame($contexto['camaraObjetivo']->id, $tarea->camara_destino_id);
        $this->assertNull($tarea->posicion_destino_id);
        $this->assertContains($tarea->folio_id, collect($contexto['foliosFuera'])->pluck('id')->all());
    }

    public function test_pallets_sin_ubicacion_siguen_contando_en_el_denominador_del_umbral(): void
    {
        $contexto = $this->crearContexto(total: 5, concentrados: 3, fuera: 0, sinUbicacion: 2);

        $plan = app(ServicioPlanConcentracionCarga::class)->sincronizar(
            $contexto['carga'],
            $contexto['usuario'],
        );

        $this->assertNotNull($plan);
        $this->assertSame(5, $plan->contexto['total']);
        $this->assertSame(3, $plan->contexto['concentrados']);
        $this->assertSame(60, $plan->contexto['porcentaje_actual']);
        $this->assertFalse($plan->estado->esFinal());
        $this->assertSame(0, $plan->tareas()->count());
    }

    public function test_servidor_rechaza_destino_que_no_amplia_el_grupo_y_acepta_un_vecino(): void
    {
        $contexto = $this->crearContexto(total: 10, concentrados: 7, fuera: 3);
        $plan = app(ServicioPlanConcentracionCarga::class)->sincronizar(
            $contexto['carga'],
            $contexto['usuario'],
        );
        $this->assertNotNull($plan);
        $tarea = $plan->tareas()->sole();
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-254',
            'nombre' => 'Tablet concentración 254',
            'activo' => true,
        ]);
        $operador = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $servicio = app(ServicioPlanesOperacionales::class);
        $tarea = $servicio->asumir($tarea, $operador, $dispositivo);
        $lejana = $contexto['posicionesObjetivo'][9];
        $vecina = $contexto['posicionesObjetivo'][7];

        try {
            $servicio->materializarDestino(
                $tarea,
                $lejana,
                $operador,
                $dispositivo,
            );
            $this->fail('El servidor aceptó una posición que no amplía el grupo principal.');
        } catch (ConflictoOperacion $exception) {
            $this->assertStringContainsString('no amplía físicamente', $exception->getMessage());
        }

        $materializada = $servicio->materializarDestino(
            $tarea->refresh(),
            $vecina,
            $operador,
            $dispositivo,
        );

        $this->assertSame($vecina->id, $materializada->posicion_destino_id);
        $this->assertSame($contexto['camaraObjetivo']->id, $materializada->camara_destino_id);
        $this->assertSame('asumida', $materializada->estado->value);
    }

    /** @return array<string, mixed> */
    private function crearContexto(
        int $total,
        int $concentrados,
        int $fuera,
        int $sinUbicacion = 0,
    ): array {
        config([
            'planificador.mode' => 'guided',
            'planificador.generacion_automatica' => true,
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.frontier_max' => 4,
        ]);
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-254',
            'nombre' => 'Temporada concentración 254',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camaraObjetivo = $this->crearCamara(
            'CAM-254-A',
            'Cámara objetivo concentración',
            1,
            10,
            $usuario,
        );
        $camaraFuera = $this->crearCamara(
            'CAM-254-B',
            'Cámara pallets dispersos',
            max(1, $fuera),
            1,
            $usuario,
        );
        $posicionesObjetivo = $this->crearPosicionesBanda(
            $camaraObjetivo,
            banda: 1,
            cantidad: 10,
        );
        $posicionesFuera = [];
        for ($indice = 1; $indice <= max(1, $fuera); $indice++) {
            $posicionesFuera[] = Posicion::create([
                'camara_id' => $camaraFuera->id,
                'banda' => $indice,
                'posicion' => 1,
                'nivel' => 1,
                'etiqueta' => sprintf('B%02d-P01-N1', $indice),
            ]);
        }
        $carga = Carga::create([
            'temporada_id' => $temporada->id,
            'codigo' => 'CAR-254-000001',
            'estado' => EstadoCarga::Pendiente,
            'prioridad' => PrioridadCarga::Alta,
            'camara_objetivo_id' => $camaraObjetivo->id,
            'version' => 1,
            'creada_por_user_id' => $usuario->id,
            'actualizada_por_user_id' => $usuario->id,
            'publicada_por_user_id' => $usuario->id,
            'publicada_at' => now(),
        ]);

        $folios = [];
        $foliosFuera = [];
        for ($indice = 1; $indice <= $total; $indice++) {
            $folio = Folio::create([
                'temporada_id' => $temporada->id,
                'numero_folio' => sprintf('PAL-254-%03d', $indice),
                'tipo_bulto' => TipoBulto::Pallet,
                'fecha_ingreso' => now()->subDay()->addMinutes($indice),
                'activo' => true,
                'marca' => 'MACE',
                'exportadora' => 'Exportadora 254',
            ]);
            $asignacion = CargaFolio::create([
                'carga_id' => $carga->id,
                'folio_id' => $folio->id,
                'estado' => EstadoCargaFolio::Pendiente,
                'asignado_por_user_id' => $usuario->id,
                'asignado_at' => now()->addSeconds($indice),
            ]);
            ReservaCargaFolio::create([
                'folio_id' => $folio->id,
                'carga_folio_id' => $asignacion->id,
            ]);

            if ($indice <= $concentrados) {
                $this->ubicarSinEventos($folio, $camaraObjetivo, $posicionesObjetivo[$indice - 1]);
            } elseif ($indice <= $concentrados + $fuera) {
                $posicion = $posicionesFuera[$indice - $concentrados - 1];
                $this->ubicarSinEventos($folio, $camaraFuera, $posicion);
                $foliosFuera[] = $folio;
            }

            $folios[] = $folio;
        }

        $this->assertSame($total, $concentrados + $fuera + $sinUbicacion);

        return compact(
            'temporada',
            'usuario',
            'camaraObjetivo',
            'camaraFuera',
            'posicionesObjetivo',
            'posicionesFuera',
            'carga',
            'folios',
            'foliosFuera',
        );
    }

    private function crearCamara(
        string $codigo,
        string $nombre,
        int $bandas,
        int $posiciones,
        User $usuario,
    ): Camara {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'contenido' => ContenidoCamara::Productos,
            'estado' => 'activa',
            'cantidad_bandas' => $bandas,
            'posiciones_por_banda' => $posiciones,
            'cantidad_niveles' => 1,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
        app(ServicioBandasOperacionales::class)->sincronizar($camara, $usuario);

        return $camara;
    }

    /** @return array<int, Posicion> */
    private function crearPosicionesBanda(Camara $camara, int $banda, int $cantidad): array
    {
        $posiciones = [];
        for ($indice = 1; $indice <= $cantidad; $indice++) {
            $posiciones[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => $banda,
                'posicion' => $indice,
                'nivel' => 1,
                'etiqueta' => sprintf('B%02d-P%02d-N1', $banda, $indice),
            ]);
        }

        return $posiciones;
    }

    private function ubicarSinEventos(Folio $folio, Camara $camara, Posicion $posicion): void
    {
        UbicacionActual::withoutEvents(fn (): UbicacionActual => UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]));
    }
}
