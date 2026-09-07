<?php

namespace Tests\Feature\Api;

use App\Enums\AccionResolucionDiscrepancia;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\DiscrepanciaManiobra;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\ManiobraOperacional;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscrepanciaManiobraApiTest extends TestCase
{
    use RefreshDatabase;

    private int $secuencia = 0;

    public function test_bandeja_exige_supervision_y_publica_solo_la_temporada_activa(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $camarero = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $temporadaActiva = Temporada::query()->where('activa', true)->firstOrFail();
        $temporadaHistorica = Temporada::create([
            'codigo' => 'HIST-DISC',
            'nombre' => 'Temporada histórica',
            'activa' => false,
        ]);

        $abierta = $this->crearDiscrepancia($temporadaActiva, 'PAL-DISC-ABIERTA');
        $this->crearDiscrepancia($temporadaActiva, 'PAL-DISC-RESUELTA', true, $supervisor);
        $this->crearDiscrepancia($temporadaHistorica, 'PAL-DISC-HISTORICA');

        $this->getJson('/api/discrepancias-maniobra')->assertUnauthorized();
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/discrepancias-maniobra')
            ->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/discrepancias-maniobra')
            ->assertOk()
            ->assertJsonPath('resumen.abiertas', 1)
            ->assertJsonPath('resumen.resueltas', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $abierta->id)
            ->assertJsonPath('data.0.folio.numero', 'PAL-DISC-ABIERTA')
            ->assertJsonPath('data.0.maniobra.estado', 'pausada_discrepancia')
            ->assertJsonPath('data.0.maniobra.version', 3)
            ->assertJsonPath('data.0.tarea.estado', 'en_proceso')
            ->assertJsonPath('data.0.restricciones.cancelar', 'tarea_en_proceso')
            ->assertJsonMissing(['numero' => 'PAL-DISC-HISTORICA']);
    }

    public function test_filtra_resueltas_y_busca_por_folio_con_auditoria_completa(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $this->crearDiscrepancia($temporada, 'PAL-DISC-OTRA');
        $resuelta = $this->crearDiscrepancia(
            $temporada,
            'PAL-DISC-BUSCADA',
            true,
            $supervisor,
        );

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/discrepancias-maniobra?estado=resuelta&q=BUSCADA')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $resuelta->id)
            ->assertJsonPath('data.0.estado', 'resuelta')
            ->assertJsonPath('data.0.accion_resolucion', 'reanudar_maniobra')
            ->assertJsonPath('data.0.resolucion', 'Posición verificada físicamente.')
            ->assertJsonPath('data.0.resuelta_por.id', $supervisor->id)
            ->assertJsonPath('data.0.maniobra.plan.tipo', 'reordenamiento_camara')
            ->assertJsonPath('data.0.tarea.origen.posicion', 'B01-P01-N1')
            ->assertJsonPath('data.0.tarea.destino.posicion', 'B01-P02-N1');
    }

    public function test_valida_los_filtros_de_la_bandeja(): void
    {
        $supervisor = User::factory()->create([
            'rol' => RolUsuario::SupervisorFrio,
            'activo' => true,
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/discrepancias-maniobra?estado=pendiente&por_pagina=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado', 'por_pagina']);
    }

    private function crearDiscrepancia(
        Temporada $temporada,
        string $numeroFolio,
        bool $resuelta = false,
        ?User $supervisor = null,
    ): DiscrepanciaManiobra {
        $this->secuencia++;
        $indice = str_pad((string) $this->secuencia, 2, '0', STR_PAD_LEFT);
        $reportante = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $camara = Camara::create([
            'codigo' => "CAM-DISC-{$indice}",
            'nombre' => "Cámara discrepancia {$indice}",
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 2,
            'cantidad_niveles' => 1,
        ]);
        $origen = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => 'B01-P01-N1',
        ]);
        $destino = Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 2,
            'nivel' => 1,
            'etiqueta' => 'B01-P02-N1',
        ]);
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => "TAB-DISC-{$indice}",
            'nombre' => "Tablet discrepancia {$indice}",
        ]);
        $plan = PlanOperacional::create([
            'temporada_id' => $temporada->id,
            'tipo' => 'reordenamiento_camara',
            'estado' => $resuelta ? 'en_ejecucion' : 'pausado',
            'prioridad' => 'normal',
            'titulo' => "Plan discrepancia {$indice}",
            'creado_por_user_id' => $reportante->id,
            'programado_at' => now(),
        ]);
        $maniobra = ManiobraOperacional::create([
            'plan_operacional_id' => $plan->id,
            'creado_por_user_id' => $reportante->id,
            'estado' => $resuelta ? 'pendiente' : 'pausada_discrepancia',
            'prioridad' => 'normal',
            'candidate_key' => "discrepancia-{$indice}",
            'titulo' => "Mover {$numeroFolio}",
            'costo_movimientos' => 1,
            'version' => 3,
        ]);
        $tarea = TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'maniobra_operacional_id' => $maniobra->id,
            'secuencia' => 1,
            'secuencia_maniobra' => 1,
            'tipo_movimiento' => 'reubicacion',
            'tipo_paso_maniobra' => 'movimiento_permanente',
            'estado' => $resuelta ? 'pendiente' : 'en_proceso',
            'prioridad' => 'normal',
            'folio_id' => $folio->id,
            'camara_origen_id' => $camara->id,
            'posicion_origen_id' => $origen->id,
            'camara_destino_id' => $camara->id,
            'posicion_destino_id' => $destino->id,
        ]);

        return DiscrepanciaManiobra::create([
            'maniobra_operacional_id' => $maniobra->id,
            'tarea_movimiento_id' => $tarea->id,
            'folio_id' => $folio->id,
            'tipo' => 'posicion_no_coincide',
            'detalle' => "La posición de {$numeroFolio} no coincide.",
            'estado' => $resuelta ? 'resuelta' : 'abierta',
            'reportada_por_user_id' => $reportante->id,
            'dispositivo_id' => $dispositivo->id,
            'reportada_at' => now()->subMinutes($this->secuencia),
            'resuelta_por_user_id' => $resuelta ? $supervisor?->id : null,
            'resuelta_at' => $resuelta ? now() : null,
            'accion_resolucion' => $resuelta
                ? AccionResolucionDiscrepancia::ReanudarManiobra
                : null,
            'resolucion' => $resuelta ? 'Posición verificada físicamente.' : null,
        ]);
    }
}
