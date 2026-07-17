<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoCarga;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\PrioridadCarga;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoEventoCarga;
use App\Events\EventoCargaRegistrado;
use App\Listeners\CrearNotificacionesOperacionales;
use App\Models\Carga;
use App\Models\EventoCarga;
use App\Models\Folio;
use App\Models\NotificacionOperacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionesOperacionalesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicacion_es_visible_para_frio_y_lectura_es_individual_e_idempotente(): void
    {
        $creador = $this->usuario(RolUsuario::Despachador);
        $camarero = $this->usuario(RolUsuario::CamareroFrio);
        $otroCamarero = $this->usuario(RolUsuario::CamareroFrio);
        $materiales = $this->usuario(RolUsuario::CamareroMateriales);
        $carga = $this->carga($creador);
        $evento = $this->evento($carga, $creador, TipoEventoCarga::Publicada, [
            'version' => 3,
            'cantidad_folios' => 12,
        ]);
        $listener = app(CrearNotificacionesOperacionales::class);

        $listener->handle(new EventoCargaRegistrado($evento));
        $listener->handle(new EventoCargaRegistrado($evento));

        $this->assertSame(1, NotificacionOperacional::query()->count());
        $notificacionId = $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 1)
            ->assertJsonPath('data.0.tipo', 'carga_publicada')
            ->assertJsonPath('data.0.carga.codigo', 'CAR-000001')
            ->assertJsonPath('data.0.leida_at', null)
            ->json('data.0.id');

        $this->actingAs($materiales, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 0)
            ->assertJsonCount(0, 'data');

        $rutaLectura = "/api/notificaciones-operacionales/{$notificacionId}/leer";
        $this->actingAs($camarero, 'sanctum')
            ->postJson($rutaLectura)
            ->assertOk()
            ->assertJsonPath('data.confirmada_at', null);
        $this->actingAs($camarero, 'sanctum')
            ->postJson($rutaLectura)
            ->assertOk();
        $this->assertDatabaseCount('lecturas_notificaciones_operacionales', 1);

        $confirmada = $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/notificaciones-operacionales/{$notificacionId}/confirmar")
            ->assertOk();
        $this->assertIsString($confirmada->json('data.confirmada_at'));
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 0);

        $this->actingAs($otroCamarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 1)
            ->assertJsonPath('data.0.leida_at', null);
        $this->actingAs($materiales, 'sanctum')
            ->postJson($rutaLectura)
            ->assertNotFound();
    }

    public function test_incidencia_alerta_a_oficina_y_su_resolucion_retorna_al_equipo_de_frio(): void
    {
        $despachador = $this->usuario(RolUsuario::Despachador);
        $supervisor = $this->usuario(RolUsuario::SupervisorFrio);
        $camarero = $this->usuario(RolUsuario::CamareroFrio);
        $carga = $this->carga($despachador);
        $folio = Folio::create([
            'numero_folio' => 'FOLIO-NOT-01',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
        $listener = app(CrearNotificacionesOperacionales::class);
        $reporte = $this->evento(
            $carga,
            $camarero,
            TipoEventoCarga::IncidenciaReportada,
            ['version' => 4, 'tipo' => 'zuncho_roto'],
            $folio,
        );
        $listener->handle(new EventoCargaRegistrado($reporte));

        $this->assertSame(3, NotificacionOperacional::query()->count());
        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'incidencia_carga_reportada')
            ->assertJsonPath('data.0.folio.numero_folio', 'FOLIO-NOT-01');
        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $resolucion = $this->evento(
            $carga,
            $despachador,
            TipoEventoCarga::IncidenciaResuelta,
            ['version' => 5, 'resolucion' => 'reparado'],
            $folio,
        );
        $listener->handle(new EventoCargaRegistrado($resolucion));

        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'incidencia_carga_resuelta')
            ->assertJsonPath('data.0.severidad', 'exito');
    }

    public function test_cambio_real_de_prioridad_genera_alerta_y_un_cambio_neutro_no(): void
    {
        $despachador = $this->usuario(RolUsuario::Despachador);
        $camarero = $this->usuario(RolUsuario::CamareroFrio);
        $carga = $this->carga($despachador);
        $listener = app(CrearNotificacionesOperacionales::class);
        $cambio = $this->evento($carga, $despachador, TipoEventoCarga::Actualizada, [
            'version' => 2,
            'prioridad_anterior' => 'normal',
            'prioridad_nueva' => 'urgente',
        ]);
        $sinCambio = $this->evento($carga, $despachador, TipoEventoCarga::Actualizada, [
            'version' => 3,
            'prioridad_anterior' => 'urgente',
            'prioridad_nueva' => 'urgente',
        ]);

        $listener->handle(new EventoCargaRegistrado($cambio));
        $listener->handle(new EventoCargaRegistrado($sinCambio));

        $this->assertSame(1, NotificacionOperacional::query()->count());
        $this->actingAs($camarero, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'prioridad_carga_cambiada')
            ->assertJsonPath('data.0.severidad', 'critica');
    }

    private function usuario(RolUsuario $rol): User
    {
        return User::factory()->create(['rol' => $rol, 'activo' => true]);
    }

    private function carga(User $usuario): Carga
    {
        return Carga::create([
            'codigo' => 'CAR-000001',
            'estado' => EstadoCarga::Pendiente,
            'prioridad' => PrioridadCarga::Normal,
            'version' => 1,
            'creada_por_user_id' => $usuario->id,
            'actualizada_por_user_id' => $usuario->id,
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function evento(
        Carga $carga,
        User $usuario,
        TipoEventoCarga $tipo,
        array $datos,
        ?Folio $folio = null,
    ): EventoCarga {
        return EventoCarga::create([
            'carga_id' => $carga->id,
            'folio_id' => $folio?->id,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'datos' => $datos,
        ]);
    }
}
