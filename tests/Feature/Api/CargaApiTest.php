<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\RolUsuario;
use App\Enums\TipoBulto;
use App\Models\Camara;
use App\Models\Carga;
use App\Models\Dispositivo;
use App\Models\EventoCarga;
use App\Models\Folio;
use App\Models\Posicion;
use App\Models\User;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioSesionEstiba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CargaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_despachador_crea_publica_y_expone_la_carga_en_tablet_y_plano(): void
    {
        $despachador = $this->despachador();
        [$camara, $folio] = $this->crearFolioUbicado('FOLIO-CARGA-01');

        $creada = $this->actingAs($despachador, 'sanctum')
            ->postJson('/api/cargas', [
                'numero_orden_externa' => 'OE-1001',
                'prioridad' => 'alta',
                'camara_objetivo_id' => $camara->id,
                'observacion' => 'Preparar para embarque.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'CAR-000001')
            ->assertJsonPath('data.estado', 'borrador');

        $cargaId = $creada->json('data.id');

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$cargaId}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.total_folios', 1)
            ->assertJsonPath('data.folios.0.ubicacion.camara.codigo', 'CAM-01');

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/cargas/pendientes')
            ->assertJsonMissing(['codigo' => 'CAR-000001']);

        $this->actingAs($despachador, 'sanctum')
            ->getJson("/api/camaras/{$camara->id}/plano")
            ->assertOk()
            ->assertJsonPath(
                'data.posiciones.0.folio.carga_actual',
                null,
            );

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$cargaId}/publicar", [
                'version_esperada' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.total_folios', 1);

        $this->actingAs($despachador, 'sanctum')
            ->getJson('/api/cargas/pendientes')
            ->assertOk()
            ->assertJsonPath('data.0.codigo', 'CAR-000001')
            ->assertJsonPath('data.0.distribucion.0.cantidad', 1);

        $this->actingAs($despachador, 'sanctum')
            ->getJson("/api/camaras/{$camara->id}/plano")
            ->assertOk()
            ->assertJsonPath(
                'data.posiciones.0.folio.carga_actual.codigo',
                'CAR-000001',
            );
    }

    public function test_un_folio_no_puede_pertenecer_a_dos_cargas_activas(): void
    {
        $despachador = $this->despachador();
        [, $folio] = $this->crearFolioUbicado('FOLIO-UNICO');
        $primera = $this->crearCarga($despachador);
        $segunda = $this->crearCarga($despachador);

        $this->assertSame('CAR-000001', $primera->codigo);
        $this->assertSame('CAR-000002', $segunda->codigo);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$primera->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk();

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$segunda->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'folios_no_asignables')
            ->assertJsonPath('errores.0.codigo', 'asignado_otra_carga');
    }

    public function test_cancelar_libera_los_folios_y_conserva_eventos(): void
    {
        $despachador = $this->despachador();
        [, $folio] = $this->crearFolioUbicado('FOLIO-LIBERADO');
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk();

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/publicar", [
                'version_esperada' => 2,
            ])
            ->assertOk();

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/cancelar", [
                'version_esperada' => 3,
                'motivo' => 'Orden reemplazada por el despacho.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada')
            ->assertJsonPath('data.version', 4);

        $this->assertDatabaseMissing('carga_folios', [
            'folio_id' => $folio->id,
        ]);
        $this->assertTrue(
            EventoCarga::query()
                ->where('carga_id', $carga->id)
                ->where('tipo', 'cancelada')
                ->exists(),
        );
        $this->assertTrue(
            EventoCarga::query()
                ->where('carga_id', $carga->id)
                ->where('folio_id', $folio->id)
                ->where('tipo', 'folio_desasignado')
                ->exists(),
        );

        $nueva = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$nueva->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk();
    }

    public function test_rechaza_folios_no_disponibles_o_sin_ubicacion(): void
    {
        $despachador = $this->despachador();
        $carga = $this->crearCarga($despachador);
        $bloqueado = Folio::create([
            'numero_folio' => 'FOLIO-BLOQUEADO',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Bloqueado,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$bloqueado->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'folios_no_asignables')
            ->assertJsonPath('errores.0.codigo', 'estado_no_disponible');

        $sinUbicacion = Folio::create([
            'numero_folio' => 'FOLIO-SIN-UBICACION',
            'tipo_bulto' => TipoBulto::Pallet,
            'estado_operacional' => EstadoOperacionalFolio::Disponible,
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$sinUbicacion->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'folios_no_asignables')
            ->assertJsonPath('errores.0.codigo', 'sin_ubicacion');
    }

    public function test_operador_no_gestiona_cargas_y_el_lote_no_supera_26_folios(): void
    {
        $operador = User::factory()->create([
            'rol' => RolUsuario::Operador,
            'activo' => true,
        ]);

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/cargas', [])
            ->assertForbidden();

        $despachador = $this->despachador();
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => collect(range(1, 27))
                    ->map(fn (int $numero): string => "FOLIO-{$numero}")
                    ->all(),
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folios');
    }

    public function test_el_lote_es_atomico_y_entrega_errores_por_folio(): void
    {
        $despachador = $this->despachador();
        [, $folio] = $this->crearFolioUbicado('FOLIO-VALIDO');
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$folio->numero_folio, 'FOLIO-INEXISTENTE'],
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'folios_no_asignables')
            ->assertJsonPath('errores.0.folio', 'FOLIO-INEXISTENTE')
            ->assertJsonPath('errores.0.codigo', 'no_existe');

        $this->assertDatabaseMissing('carga_folios', [
            'carga_id' => $carga->id,
        ]);
        $this->assertSame(1, $carga->refresh()->version);
    }

    public function test_una_carga_pendiente_admite_cambios_con_control_de_version(): void
    {
        $despachador = $this->despachador();
        [, $folio] = $this->crearFolioUbicado('FOLIO-VERSIONADO');
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/publicar", [
                'version_esperada' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 3);

        $this->actingAs($despachador, 'sanctum')
            ->putJson("/api/cargas/{$carga->id}", [
                'prioridad' => 'urgente',
                'version_esperada' => 2,
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'conflicto_operacional');

        $this->actingAs($despachador, 'sanctum')
            ->putJson("/api/cargas/{$carga->id}", [
                'prioridad' => 'urgente',
                'version_esperada' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.prioridad', 'urgente')
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.actualizada_por.id', $despachador->id);

        $this->actingAs($despachador, 'sanctum')
            ->deleteJson("/api/cargas/{$carga->id}/folios/{$folio->id}", [
                'version_esperada' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertSame(4, $carga->refresh()->version);
    }

    public function test_publicar_revalida_el_estado_actual_de_todos_los_folios(): void
    {
        $despachador = $this->despachador();
        [, $folio] = $this->crearFolioUbicado('FOLIO-CAMBIO-ESTADO');
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [$folio->numero_folio],
                'version_esperada' => 1,
            ])
            ->assertOk();

        $folio->update(['estado_operacional' => EstadoOperacionalFolio::Bloqueado]);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/publicar", [
                'version_esperada' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'folios_no_asignables')
            ->assertJsonPath('errores.0.codigo', 'estado_no_disponible');

        $this->assertSame('borrador', $carga->refresh()->estado->value);
        $this->assertSame(2, $carga->version);
    }

    public function test_el_despachador_accede_a_oficina_sin_administrar_camaras(): void
    {
        $despachador = $this->despachador();

        $this->postJson('/api/acceso-oficina', [
            'email' => $despachador->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_gestionar_cargas', true)
            ->assertJsonPath('usuario.puede_configurar_camaras', false)
            ->assertJsonPath('usuario.puede_administrar_camaras', false);

        $this->actingAs($despachador, 'sanctum')
            ->postJson('/api/configuracion/camaras', [])
            ->assertForbidden();
    }

    public function test_los_duplicados_se_detectan_despues_de_normalizar_el_folio(): void
    {
        $despachador = $this->despachador();
        $carga = $this->crearCarga($despachador);

        $this->actingAs($despachador, 'sanctum')
            ->postJson("/api/cargas/{$carga->id}/folios", [
                'folios' => [' FOLIO-DUPLICADO ', 'FOLIO-DUPLICADO'],
                'version_esperada' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folios.1');
    }

    private function despachador(): User
    {
        return User::factory()->create([
            'rol' => RolUsuario::Despachador,
            'activo' => true,
        ]);
    }

    private function crearCarga(User $usuario): Carga
    {
        $id = $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/cargas', [])
            ->assertCreated()
            ->json('data.id');

        return Carga::query()->findOrFail($id);
    }

    /**
     * @return array{Camara, Folio}
     */
    private function crearFolioUbicado(string $numeroFolio): array
    {
        $operador = User::factory()->create([
            'rol' => RolUsuario::Operador,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-'.Str::upper(Str::random(6)),
            'nombre' => 'Tablet de prueba',
        ]);
        $camara = Camara::create([
            'codigo' => 'CAM-01',
            'nombre' => 'Cámara 01',
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
        $sesion = app(ServicioSesionEstiba::class)
            ->abrir($camara, $operador, $dispositivo);
        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: $numeroFolio,
            tipoBulto: TipoBulto::Pallet,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
        );

        return [$camara, $movimiento->folio];
    }
}
