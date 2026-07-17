<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Anden;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Dispositivo;
use App\Models\Folio;
use App\Models\IncidenciaCargaFolio;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Cargas\ServicioCarga;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DespachoFrigorificoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicar_genera_tareas_y_el_reporte_de_incidencia_es_idempotente(): void
    {
        $contexto = $this->crearContextoFrio(2);
        $carga = $this->crearCargaPublicada(
            $contexto['despachador'],
            $contexto['anden'],
            $contexto['folios'],
        );
        $asignacion = $carga->asignacionesActuales()->firstOrFail();

        $this->assertDatabaseHas('tareas_carga', [
            'carga_id' => $carga->id,
            'camara_origen_id' => $contexto['camara']->id,
            'estado' => 'pendiente',
        ]);

        $operacion = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacion,
            'tipo' => 'zuncho_roto',
            'descripcion' => 'Zuncho lateral cortado.',
            'sesion_estiba_id' => $contexto['sesion']->id,
        ];
        $ruta = "/api/cargas/asignaciones/{$asignacion->id}/incidencias";

        $incidenciaId = $this->withToken($contexto['token'])
            ->postJson($ruta, $payload)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'abierta')
            ->assertJsonPath('data.tipo', 'zuncho_roto')
            ->json('data.id');

        $this->withToken($contexto['token'])
            ->postJson($ruta, $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $incidenciaId);

        $this->assertSame(1, IncidenciaCargaFolio::query()->count());
        $this->assertSame('con_incidencia', $asignacion->refresh()->estado->value);
        $this->assertSame('en_preparacion', $carga->refresh()->estado->value);

        $this->withToken($contexto['token'])
            ->postJson($ruta, [
                ...$payload,
                'descripcion' => 'Mismo UUID con otro contenido.',
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');
    }

    public function test_resuelve_incidencias_como_reparacion_despacho_parcial_o_reemplazo(): void
    {
        $contexto = $this->crearContextoFrio(3);
        $carga = $this->crearCargaPublicada(
            $contexto['despachador'],
            $contexto['anden'],
            array_slice($contexto['folios'], 0, 2),
        );
        $asignacion = $carga->asignacionesActuales()->firstOrFail();
        $incidencia = $this->reportarIncidencia($contexto, $asignacion);

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson("/api/cargas/incidencias/{$incidencia->id}/resolver", [
                'operacion_id' => (string) Str::uuid(),
                'resolucion' => 'reparado',
                'observacion' => 'Pallet reparado en piso.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'resuelta')
            ->assertJsonPath('data.tipo_resolucion', 'reparado');

        $this->assertSame('pendiente', $asignacion->refresh()->estado->value);
        $segundaIncidencia = $this->reportarIncidencia($contexto, $asignacion);

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson("/api/cargas/incidencias/{$segundaIncidencia->id}/resolver", [
                'operacion_id' => (string) Str::uuid(),
                'resolucion' => 'reemplazo',
                'folio_reemplazo_id' => $contexto['folios'][2]->id,
                'observacion' => 'Reemplazo autorizado por exportadora.',
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo_resolucion', 'reemplazo');

        $this->assertDatabaseHas('carga_folios', [
            'id' => $asignacion->id,
            'estado' => 'reemplazado',
        ]);
        $this->assertDatabaseHas('carga_folios', [
            'carga_id' => $carga->id,
            'folio_id' => $contexto['folios'][2]->id,
            'estado' => 'pendiente',
            'reemplaza_a_carga_folio_id' => $asignacion->id,
        ]);
        $this->assertDatabaseHas('reservas_carga_folio', [
            'folio_id' => $contexto['folios'][2]->id,
        ]);
        $this->assertDatabaseMissing('reservas_carga_folio', [
            'folio_id' => $asignacion->folio_id,
        ]);

        $nuevaAsignacion = CargaFolio::query()
            ->where('folio_id', $contexto['folios'][2]->id)
            ->firstOrFail();
        $terceraIncidencia = $this->reportarIncidencia($contexto, $nuevaAsignacion);

        $this->actingAs($contexto['operador'], 'sanctum')
            ->postJson("/api/cargas/incidencias/{$terceraIncidencia->id}/resolver", [
                'operacion_id' => (string) Str::uuid(),
                'resolucion' => 'despacho_parcial',
            ])
            ->assertForbidden();

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson("/api/cargas/incidencias/{$terceraIncidencia->id}/resolver", [
                'operacion_id' => (string) Str::uuid(),
                'resolucion' => 'despacho_parcial',
                'observacion' => 'Cliente acepta salida parcial.',
            ])
            ->assertOk()
            ->assertJsonPath('data.tipo_resolucion', 'despacho_parcial');

        $this->assertDatabaseHas('carga_folios', [
            'id' => $nuevaAsignacion->id,
            'estado' => 'descartado',
        ]);
    }

    public function test_envia_a_anden_y_cierra_la_salida_del_camion_con_trazabilidad(): void
    {
        $contexto = $this->crearContextoFrio(1);
        $folio = $contexto['folios'][0];
        $carga = $this->crearCargaPublicada(
            $contexto['despachador'],
            $contexto['anden'],
            [$folio],
        );
        $asignacion = $carga->asignacionesActuales()->firstOrFail();
        $operacionRetiro = (string) Str::uuid();
        $payloadRetiro = [
            'operacion_id' => $operacionRetiro,
            'anden_id' => $contexto['anden']->id,
            'sesion_estiba_id' => $contexto['sesion']->id,
            'version_camara_conocida' => 1,
            'generado_dispositivo_at' => now()->toAtomString(),
        ];
        $rutaRetiro = "/api/cargas/asignaciones/{$asignacion->id}/enviar-anden";

        $this->withToken($contexto['token'])
            ->postJson($rutaRetiro, $payloadRetiro)
            ->assertOk()
            ->assertJsonPath('data.estado', 'despachada')
            ->assertJsonPath('data.folios.0.estado_carga', 'en_anden')
            ->assertJsonPath('data.folios.0.anden.codigo', 'AND-01');

        $this->withToken($contexto['token'])
            ->postJson($rutaRetiro, $payloadRetiro)
            ->assertOk()
            ->assertJsonPath('data.estado', 'despachada');

        $this->assertDatabaseMissing('ubicaciones_actuales', ['folio_id' => $folio->id]);
        $this->assertDatabaseHas('movimientos', [
            'folio_id' => $folio->id,
            'tipo_movimiento' => 'retiro',
        ]);
        $this->assertSame(1, $folio->movimientos()->where('tipo_movimiento', 'retiro')->count());

        $operacionCierre = (string) Str::uuid();
        $payloadCierre = [
            'operacion_id' => $operacionCierre,
            'patente' => 'ab-cd-12',
            'conductor' => 'María Pérez',
        ];
        $rutaCierre = "/api/cargas/{$carga->id}/cerrar-despacho";

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson($rutaCierre, $payloadCierre)
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada')
            ->assertJsonPath('data.cierre.patente', 'AB-CD-12')
            ->assertJsonPath('data.cierre.conductor', 'María Pérez');

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson($rutaCierre, $payloadCierre)
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada');

        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'estado_operacional' => EstadoOperacionalFolio::Despachado->value,
            'activo' => false,
        ]);
        $this->assertDatabaseMissing('reservas_carga_folio', ['folio_id' => $folio->id]);

        $this->actingAs($contexto['despachador'], 'sanctum')
            ->postJson($rutaCierre, [
                ...$payloadCierre,
                'conductor' => 'Otra persona',
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');
    }

    /** @return array<string, mixed> */
    private function crearContextoFrio(int $cantidadFolios): array
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $despachador = User::factory()->create([
            'rol' => RolUsuario::Despachador,
            'activo' => true,
        ]);
        $operador = User::factory()->create([
            'rol' => RolUsuario::CamareroFrio,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-01',
            'nombre' => 'Tablet cámara 01',
        ]);
        $token = $operador
            ->crearTokenParaDispositivo($dispositivo, 'tablet-01')
            ->plainTextToken;
        $camara = Camara::create([
            'codigo' => 'CAM-01',
            'nombre' => 'Cámara de tránsito 01',
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => $cantidadFolios,
            'cantidad_niveles' => 1,
        ]);
        $sesion = app(ServicioSesionEstiba::class)->abrir(
            $camara,
            $operador,
            $dispositivo,
        );
        $folios = [];

        for ($indice = 1; $indice <= $cantidadFolios; $indice++) {
            $posicion = Posicion::create([
                'camara_id' => $camara->id,
                'banda' => 1,
                'posicion' => $indice,
                'nivel' => 1,
                'etiqueta' => sprintf('B01-P%02d-N1', $indice),
            ]);
            $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
                operacionId: (string) Str::uuid(),
                numeroFolio: sprintf('FOLIO-DESP-%02d', $indice),
                tipoBulto: TipoBulto::Pallet,
                posicionDestino: $posicion,
                sesionDestino: $sesion,
                usuario: $operador,
                dispositivo: $dispositivo,
                versionDestinoConocida: $indice - 1,
                generadoDispositivoAt: now(),
                datosFolio: [
                    'variedad' => 'Santina',
                    'calibre' => '2J',
                    'marca' => 'Marca prueba',
                    'exportadora' => 'Exportadora prueba',
                ],
            );
            $folios[] = $movimiento->folio;
        }

        $anden = Anden::create([
            'codigo' => 'AND-01',
            'nombre' => 'Andén 01',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);

        return compact(
            'administrador',
            'despachador',
            'operador',
            'dispositivo',
            'token',
            'camara',
            'sesion',
            'anden',
            'folios',
        );
    }

    /**
     * @param  array<int, Folio>  $folios
     */
    private function crearCargaPublicada(User $despachador, Anden $anden, array $folios): Carga
    {
        $servicio = app(ServicioCarga::class);
        $carga = $servicio->crear(['anden_previsto_id' => $anden->id], $despachador);
        $carga = $servicio->agregarFolios(
            $carga,
            collect($folios)->pluck('numero_folio')->all(),
            $despachador,
            1,
        );

        return $servicio->publicar($carga, $despachador, $carga->version);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function reportarIncidencia(array $contexto, CargaFolio $asignacion): IncidenciaCargaFolio
    {
        $id = $this->withToken($contexto['token'])
            ->postJson("/api/cargas/asignaciones/{$asignacion->id}/incidencias", [
                'operacion_id' => (string) Str::uuid(),
                'tipo' => 'caja_aplastada',
                'sesion_estiba_id' => $contexto['sesion']->id,
            ])
            ->assertCreated()
            ->json('data.id');

        return IncidenciaCargaFolio::query()->findOrFail($id);
    }
}
