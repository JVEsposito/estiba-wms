<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoCargaFolio;
use App\Enums\EstadoManiobraOperacional;
use App\Enums\EstadoPlanOperacional;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadCarga;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Http\Resources\TareaMovimientoResource;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\ReservaCargaFolio;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Services\Camaras\ServicioBandasOperacionales;
use App\Services\Cargas\ServicioPlanConcentracionCarga;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioPlanesOperacionales;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResolucionCruzadaCargasTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocker_con_tarea_reversible_se_resuelve_en_acopio_de_su_carga(): void
    {
        $contexto = $this->crearContextoCruzado();
        $servicio = app(ServicioPlanConcentracionCarga::class);

        $planSecundario = $servicio->sincronizar(
            $contexto['cargaSecundaria'],
            $contexto['usuario'],
        );
        $this->assertNotNull($planSecundario);
        $tareaOriginal = $planSecundario->tareas()->sole();
        $this->assertSame($contexto['blocker']->id, $tareaOriginal->folio_id);
        $this->assertSame(EstadoTareaMovimiento::Pendiente, $tareaOriginal->estado);

        $planPrincipal = $servicio->sincronizar(
            $contexto['cargaPrincipal'],
            $contexto['usuario'],
        );

        $this->assertNotNull($planPrincipal);
        $maniobra = $planPrincipal->maniobras()
            ->where('estado', EstadoManiobraOperacional::Pendiente->value)
            ->sole();
        $pasos = $maniobra->pasos()->get();
        $pasoCruzado = $pasos->firstWhere('folio_id', $contexto['blocker']->id);

        $this->assertNotNull($pasoCruzado);
        $this->assertSame(
            $contexto['posicionesSecundarias'][1]->id,
            $pasoCruzado->posicion_destino_id,
        );
        $this->assertSame('blocker_destino_util', $pasoCruzado->contexto['tipo_decision']);
        $this->assertSame('acopio_carga_activa', $pasoCruzado->contexto['beneficio_secundario']);
        $this->assertTrue($pasoCruzado->contexto['resolucion_cruzada_objetivos']);
        $this->assertSame(
            $contexto['cargaSecundaria']->id,
            $pasoCruzado->contexto['carga_beneficiada_id'],
        );
        $this->assertSame(
            $contexto['cargaSecundaria']->codigo,
            $pasoCruzado->contexto['carga_beneficiada_codigo'],
        );
        $this->assertStringContainsString(
            "acopio de {$contexto['cargaSecundaria']->codigo}",
            $pasoCruzado->instruccion,
        );
        $this->assertTrue($maniobra->contexto['resolucion_cruzada_objetivos']);
        $this->assertContains(
            $contexto['cargaSecundaria']->id,
            $maniobra->contexto['cargas_beneficiadas_ids'],
        );
        $objetivos = $maniobra->objetivos()->get();
        $this->assertCount(2, $objetivos);
        $this->assertEqualsCanonicalizing(
            [$planPrincipal->id, $planSecundario->id],
            $objetivos->pluck('id')->all(),
        );
        $this->assertSame(
            1000,
            $objetivos->firstWhere('id', $planPrincipal->id)->pivot->beneficio_estimado,
        );
        $this->assertSame(
            300,
            $objetivos->firstWhere('id', $planSecundario->id)->pivot->beneficio_estimado,
        );
        $this->assertTrue(
            (bool) $objetivos->firstWhere('id', $planPrincipal->id)->pivot->es_principal,
        );
        $this->assertFalse(
            (bool) $objetivos->firstWhere('id', $planSecundario->id)->pivot->es_principal,
        );
        $this->assertSame('urgente', $maniobra->prioridad->value);
        $this->assertSame(1300, $maniobra->beneficio_estimado);
        $this->assertTrue($maniobra->pasos()->get()->every(
            fn ($paso): bool => $paso->prioridad->value === 'urgente',
        ));
        $this->assertTrue($planSecundario->maniobrasResolutorias()
            ->whereKey($maniobra->id)
            ->exists());
        $this->assertSame(1, TareaMovimiento::query()
            ->where('folio_id', $contexto['blocker']->id)
            ->whereIn('estado', [
                EstadoTareaMovimiento::Bloqueada->value,
                EstadoTareaMovimiento::Pendiente->value,
                EstadoTareaMovimiento::Asumida->value,
                EstadoTareaMovimiento::EnProceso->value,
            ])
            ->count());

        $tareaOriginal->refresh();
        $this->assertSame(EstadoTareaMovimiento::Cancelada, $tareaOriginal->estado);
        $this->assertSame($pasoCruzado->id, $tareaOriginal->reemplazada_por_tarea_id);
        $this->assertSame(
            EstadoManiobraOperacional::Cancelada,
            $tareaOriginal->maniobraOperacional->refresh()->estado,
        );

        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-MULTI-001',
            'nombre' => 'Tablet multiobjetivo',
        ]);
        $asumida = app(ServicioPlanesOperacionales::class)->asumir(
            $maniobra->pasos()->firstOrFail(),
            $camarero,
            $dispositivo,
        );
        $this->assertSame(
            EstadoPlanOperacional::EnEjecucion,
            $planSecundario->refresh()->estado,
        );
        $recurso = (new TareaMovimientoResource($asumida))->resolve(request());
        $this->assertCount(2, $recurso['maniobra']['objetivos']);
        $this->assertEqualsCanonicalizing(
            [$planPrincipal->id, $planSecundario->id],
            collect($recurso['maniobra']['objetivos'])->pluck('id')->all(),
        );

        $servicio->sincronizar($contexto['cargaPrincipal'], $contexto['usuario']);
        $this->assertSame(2, $maniobra->objetivos()->count());

        $sesiones = app(ServicioSesionEstiba::class);
        $sesionOrigen = $sesiones->abrir(
            $contexto['camaraOrigen'],
            $camarero,
            $dispositivo,
        );
        $sesionDestino = $sesiones->abrir(
            $contexto['camaraSecundaria'],
            $camarero,
            $dispositivo,
        );
        app(ServicioPlanesOperacionales::class)->iniciar(
            $asumida->refresh(),
            $camarero,
            $dispositivo,
        );
        app(ServicioMovimientoEstiba::class)->mover(
            operacionId: (string) Str::uuid(),
            folio: $contexto['blocker'],
            posicionDestino: $contexto['posicionesSecundarias'][1],
            sesionOrigen: $sesionOrigen,
            sesionDestino: $sesionDestino,
            usuario: $camarero,
            dispositivo: $dispositivo,
            versionOrigenConocida: $contexto['camaraOrigen']->refresh()->version_plano,
            versionDestinoConocida: $contexto['camaraSecundaria']->refresh()->version_plano,
            generadoDispositivoAt: now(),
            tareaMovimiento: $asumida->refresh(),
        );
        $servicio->sincronizar($contexto['cargaSecundaria'], $contexto['usuario']);
        $this->assertSame(
            EstadoPlanOperacional::Completado,
            $planSecundario->refresh()->estado,
        );
    }

    public function test_blocker_en_proceso_no_se_reasigna_a_otra_maniobra(): void
    {
        $contexto = $this->crearContextoCruzado();
        $servicio = app(ServicioPlanConcentracionCarga::class);

        $planSecundario = $servicio->sincronizar(
            $contexto['cargaSecundaria'],
            $contexto['usuario'],
        );
        $this->assertNotNull($planSecundario);
        $tareaOriginal = $planSecundario->tareas()->sole();
        $tareaOriginal->update([
            'estado' => EstadoTareaMovimiento::EnProceso,
            'iniciada_at' => now(),
            'version' => $tareaOriginal->version + 1,
        ]);
        $tareaOriginal->maniobraOperacional->update([
            'estado' => EstadoManiobraOperacional::EnEjecucion,
            'iniciada_at' => now(),
            'version' => $tareaOriginal->maniobraOperacional->version + 1,
        ]);

        $planPrincipal = $servicio->sincronizar(
            $contexto['cargaPrincipal'],
            $contexto['usuario'],
        );

        $this->assertNotNull($planPrincipal);
        $this->assertSame(0, $planPrincipal->maniobras()->count());
        $this->assertSame(EstadoTareaMovimiento::EnProceso, $tareaOriginal->refresh()->estado);
        $this->assertNull($tareaOriginal->reemplazada_por_tarea_id);
        $this->assertSame(
            EstadoManiobraOperacional::EnEjecucion,
            $tareaOriginal->maniobraOperacional->refresh()->estado,
        );
    }

    /** @return array<string, mixed> */
    private function crearContextoCruzado(): array
    {
        config([
            'planificador.mode' => 'guided',
            'planificador.generacion_automatica' => true,
            'planificador.compute' => 'tablet',
            'planificador.horizon' => 'rolling',
            'planificador.frontier_max' => 4,
        ]);
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-CRUZADA',
            'nombre' => 'Temporada resolución cruzada',
            'fecha_inicio' => '2026-09-01',
            'activa' => true,
        ]);
        $usuario = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $camaraPrincipal = $this->crearCamara(
            'CAM-CRUZ-A',
            'Acopio carga principal',
            1,
            5,
            $usuario,
        );
        $camaraSecundaria = $this->crearCamara(
            'CAM-CRUZ-B',
            'Acopio carga secundaria',
            1,
            3,
            $usuario,
        );
        $camaraOrigen = $this->crearCamara(
            'CAM-CRUZ-C',
            'Origen compartido',
            1,
            2,
            $usuario,
        );
        $posicionesPrincipales = $this->crearPosiciones($camaraPrincipal, 5);
        $posicionesSecundarias = $this->crearPosiciones($camaraSecundaria, 3);
        $posicionesOrigen = $this->crearPosiciones($camaraOrigen, 2);

        $cargaPrincipal = $this->crearCarga(
            $temporada,
            $usuario,
            'CAR-CRUZ-001',
            $camaraPrincipal,
        );
        $cargaSecundaria = $this->crearCarga(
            $temporada,
            $usuario,
            'CAR-CRUZ-002',
            $camaraSecundaria,
            PrioridadCarga::Urgente,
        );

        for ($indice = 0; $indice < 3; $indice++) {
            $folio = $this->crearFolio(
                $temporada,
                sprintf('PAL-CRUZ-P-%02d', $indice + 1),
            );
            $this->asignar($cargaPrincipal, $folio, $usuario);
            $this->ubicar($folio, $camaraPrincipal, $posicionesPrincipales[$indice]);
        }

        $objetivoPrincipal = $this->crearFolio($temporada, 'PAL-CRUZ-OBJETIVO');
        $this->asignar($cargaPrincipal, $objetivoPrincipal, $usuario);
        $this->ubicar($objetivoPrincipal, $camaraOrigen, $posicionesOrigen[0]);

        $sinUbicacion = $this->crearFolio($temporada, 'PAL-CRUZ-SIN-UBICACION');
        $this->asignar($cargaPrincipal, $sinUbicacion, $usuario);

        $anclaSecundaria = $this->crearFolio($temporada, 'PAL-CRUZ-ANCLA');
        $this->asignar($cargaSecundaria, $anclaSecundaria, $usuario);
        $this->ubicar($anclaSecundaria, $camaraSecundaria, $posicionesSecundarias[0]);

        $blocker = $this->crearFolio($temporada, 'PAL-CRUZ-BLOCKER');
        $this->asignar($cargaSecundaria, $blocker, $usuario);
        $this->ubicar($blocker, $camaraOrigen, $posicionesOrigen[1]);

        return compact(
            'temporada',
            'usuario',
            'cargaPrincipal',
            'cargaSecundaria',
            'camaraPrincipal',
            'camaraSecundaria',
            'camaraOrigen',
            'posicionesPrincipales',
            'posicionesSecundarias',
            'posicionesOrigen',
            'objetivoPrincipal',
            'blocker',
        );
    }

    private function crearCarga(
        Temporada $temporada,
        User $usuario,
        string $codigo,
        Camara $camaraObjetivo,
        PrioridadCarga $prioridad = PrioridadCarga::Alta,
    ): Carga {
        return Carga::create([
            'temporada_id' => $temporada->id,
            'codigo' => $codigo,
            'estado' => EstadoCarga::EnPreparacion,
            'prioridad' => $prioridad,
            'camara_objetivo_id' => $camaraObjetivo->id,
            'version' => 1,
            'creada_por_user_id' => $usuario->id,
            'actualizada_por_user_id' => $usuario->id,
            'publicada_por_user_id' => $usuario->id,
            'publicada_at' => now(),
        ]);
    }

    private function crearFolio(Temporada $temporada, string $numero): Folio
    {
        return Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
            'activo' => true,
            'marca' => 'CRUZADA',
            'exportadora' => 'Cliente cruzado',
        ]);
    }

    private function asignar(Carga $carga, Folio $folio, User $usuario): void
    {
        $asignacion = CargaFolio::create([
            'carga_id' => $carga->id,
            'folio_id' => $folio->id,
            'estado' => EstadoCargaFolio::Pendiente,
            'asignado_por_user_id' => $usuario->id,
            'asignado_at' => now(),
        ]);
        ReservaCargaFolio::create([
            'folio_id' => $folio->id,
            'carga_folio_id' => $asignacion->id,
        ]);
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
    private function crearPosiciones(Camara $camara, int $cantidad): array
    {
        $posiciones = [];
        for ($indice = 1; $indice <= $cantidad; $indice++) {
            $posiciones[] = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $indice,
                'nivel' => 1,
                'etiqueta' => sprintf('B01-P%02d-N1', $indice),
            ]);
        }

        return $posiciones;
    }

    private function ubicar(Folio $folio, Camara $camara, Posicion $posicion): void
    {
        UbicacionActual::withoutEvents(fn (): UbicacionActual => UbicacionActual::create([
            'folio_id' => $folio->id,
            'camara_id' => $camara->id,
            'posicion_id' => $posicion->id,
            'ubicado_at' => now(),
        ]));
    }
}
