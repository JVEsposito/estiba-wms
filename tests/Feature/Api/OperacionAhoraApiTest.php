<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoTareaMovimiento;
use App\Enums\PrioridadOperacional;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPlanOperacional;
use App\Models\Camara;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\PlanOperacional;
use App\Models\Posicion;
use App\Models\RegistroControlAmbiental;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
            ->assertJsonCount(0, 'data.camareros')
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
            ->assertJsonCount(1, 'data.camareros')
            ->assertJsonPath('data.camareros.0.tarea_actual', null)
            ->assertJsonPath('data.camaras.0.codigo', 'CAM-AHORA-01')
            ->assertJsonPath('data.camaras.0.ocupacion_porcentaje', 100)
            ->assertJsonPath('data.camaras.0.nivel_ocupacion', 'critica')
            ->assertJsonPath('data.camaras.0.alertas.0', 'ocupacion_critica')
            ->assertJsonPath('data.camaras.0.control_ambiental.estado', 'vigente')
            ->assertJsonPath('data.camaras.0.control_ambiental.temperaturas_c.promedio', -1);
    }

    public function test_expone_ubicacion_y_tarea_actual_de_cada_camarero_activo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00 UTC'));
        $temporada = $this->crearTemporada();
        $temporadaAnterior = Temporada::create([
            'codigo' => 'ANTERIOR',
            'nombre' => 'Temporada anterior',
            'fecha_inicio' => '2025-08-01',
            'fecha_fin' => '2026-03-31',
            'activa' => false,
        ]);
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $camarero = User::factory()->create([
            'name' => 'Camarero activo',
            'rol' => RolUsuario::CamareroFrio,
        ]);
        $camareroCerrado = User::factory()->create([
            'name' => 'Camarero cerrado',
            'rol' => RolUsuario::CamareroFrio,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TAB-ACTIVA-01',
            'nombre' => 'Tablet activa',
        ]);
        $dispositivoCerrado = Dispositivo::create([
            'codigo' => 'TAB-CERRADA-01',
            'nombre' => 'Tablet cerrada',
        ]);
        $camaraSesion = $this->crearCamara('CAM-SESION', 'Cámara de la sesión');
        $camaraOrigen = $this->crearCamara('CAM-ORIGEN', 'Cámara origen');
        $camaraDestino = $this->crearCamara('CAM-DESTINO', 'Cámara destino');
        $camaraCerrada = $this->crearCamara('CAM-CERRADA', 'Cámara cerrada');
        $sesiones = app(ServicioSesionEstiba::class);
        $sesionActiva = $sesiones->abrir($camaraSesion, $camarero, $dispositivo);
        $sesionCerrada = $sesiones->abrir(
            $camaraCerrada,
            $camareroCerrado,
            $dispositivoCerrado,
        );
        $sesiones->cerrar($sesionCerrada, $camareroCerrado);
        $planActual = $this->crearPlan($temporada, $consulta, 'Plan actual');
        $planAnterior = $this->crearPlan($temporadaAnterior, $consulta, 'Plan anterior');

        $this->crearTarea(
            $planActual,
            $temporada,
            $camarero,
            $dispositivo,
            $camaraOrigen,
            $camaraDestino,
            EstadoTareaMovimiento::Asumida,
            PrioridadOperacional::Critica,
            'PAL-ASUMIDO-001',
            now()->subMinutes(20),
        );
        $tareaActual = $this->crearTarea(
            $planActual,
            $temporada,
            $camarero,
            $dispositivo,
            $camaraOrigen,
            $camaraDestino,
            EstadoTareaMovimiento::EnProceso,
            PrioridadOperacional::Normal,
            'PAL-ACTUAL-001',
            now()->subMinutes(5),
        );
        $this->crearTarea(
            $planAnterior,
            $temporadaAnterior,
            $camarero,
            $dispositivo,
            $camaraOrigen,
            $camaraDestino,
            EstadoTareaMovimiento::EnProceso,
            PrioridadOperacional::Critica,
            'PAL-ANTERIOR-001',
            now()->subHour(),
        );

        $this->actingAs($consulta, 'sanctum')
            ->getJson('/api/operacion-ahora')
            ->assertOk()
            ->assertJsonCount(1, 'data.camareros')
            ->assertJsonPath('data.camareros.0.usuario.nombre', 'Camarero activo')
            ->assertJsonPath('data.camareros.0.dispositivo.codigo', 'TAB-ACTIVA-01')
            ->assertJsonPath('data.camareros.0.sesion.id', $sesionActiva->id)
            ->assertJsonPath('data.camareros.0.sesion.estado', 'abierta')
            ->assertJsonPath('data.camareros.0.ubicacion_actual.camara.codigo', 'CAM-SESION')
            ->assertJsonPath('data.camareros.0.tarea_actual.id', $tareaActual->id)
            ->assertJsonPath('data.camareros.0.tarea_actual.estado', 'en_proceso')
            ->assertJsonPath('data.camareros.0.tarea_actual.prioridad', 'normal')
            ->assertJsonPath('data.camareros.0.tarea_actual.plan.titulo', 'Plan actual')
            ->assertJsonPath('data.camareros.0.tarea_actual.folio.numero_folio', 'PAL-ACTUAL-001')
            ->assertJsonPath('data.camareros.0.tarea_actual.origen.camara.codigo', 'CAM-ORIGEN')
            ->assertJsonPath('data.camareros.0.tarea_actual.destino.camara.codigo', 'CAM-DESTINO');
    }

    private function crearCamara(string $codigo, string $nombre): Camara
    {
        $camara = Camara::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'contenido' => ContenidoCamara::Productos,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        Posicion::create([
            'camara_id' => $camara->id,
            'banda' => 1,
            'posicion' => 1,
            'nivel' => 1,
            'etiqueta' => $codigo.'-B01-P01-N1',
        ]);

        return $camara;
    }

    private function crearPlan(Temporada $temporada, User $creador, string $titulo): PlanOperacional
    {
        return PlanOperacional::create([
            'temporada_id' => $temporada->id,
            'tipo' => TipoPlanOperacional::ReordenamientoCamara,
            'estado' => 'en_ejecucion',
            'prioridad' => PrioridadOperacional::Normal,
            'titulo' => $titulo,
            'creado_por_user_id' => $creador->id,
            'programado_at' => now()->subHour(),
            'iniciado_at' => now()->subHour(),
        ]);
    }

    private function crearTarea(
        PlanOperacional $plan,
        Temporada $temporada,
        User $camarero,
        Dispositivo $dispositivo,
        Camara $origen,
        Camara $destino,
        EstadoTareaMovimiento $estado,
        PrioridadOperacional $prioridad,
        string $numeroFolio,
        CarbonInterface $fecha,
    ): TareaMovimiento {
        $folio = Folio::create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => TipoBulto::Pallet,
            'fecha_ingreso' => now(),
        ]);

        return TareaMovimiento::create([
            'plan_operacional_id' => $plan->id,
            'secuencia' => ((int) $plan->tareas()->max('secuencia')) + 1,
            'tipo_movimiento' => TipoMovimiento::TrasladoEntreCamaras,
            'estado' => $estado,
            'prioridad' => $prioridad,
            'folio_id' => $folio->id,
            'camara_origen_id' => $origen->id,
            'posicion_origen_id' => $origen->posiciones()->firstOrFail()->id,
            'camara_destino_id' => $destino->id,
            'posicion_destino_id' => $destino->posiciones()->firstOrFail()->id,
            'responsable_user_id' => $camarero->id,
            'dispositivo_id' => $dispositivo->id,
            'instruccion' => 'Mover '.$numeroFolio,
            'asumida_at' => $fecha,
            'iniciada_at' => $estado === EstadoTareaMovimiento::EnProceso ? $fecha : null,
        ]);
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
