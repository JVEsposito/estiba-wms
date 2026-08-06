<?php

namespace Tests\Feature\Api;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Models\CalibreValidacion;
use App\Models\Camara;
use App\Models\Cliente;
use App\Models\CsgValidacion;
use App\Models\EntregaFrutaProceso;
use App\Models\EspecieValidacion;
use App\Models\Temporada;
use App\Models\TipoResultadoPacking;
use App\Models\User;
use App\Models\VariedadValidacion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MateriaPrimaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogos_usan_etag_y_omiten_relaciones_pesadas_si_no_cambian(): void
    {
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $camara = Camara::create([
            'codigo' => 'MP-ETAG',
            'nombre' => 'Cámara ETag',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $inicial = $this->actingAs($digitador, 'sanctum')
            ->getJson('/api/materia-prima/catalogos')
            ->assertOk()
            ->assertHeader('Access-Control-Expose-Headers', 'ETag')
            ->assertJsonPath('temporada.id', $temporada->id)
            ->assertJsonPath('camaras.0.id', $camara->id);
        $etagInicial = $inicial->headers->get('ETag');

        $this->assertNotNull($etagInicial);
        $this->assertStringContainsString('private', (string) $inicial->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $inicial->headers->get('Cache-Control'));

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->withHeader('If-None-Match', $etagInicial)
            ->get('/api/materia-prima/catalogos')
            ->assertStatus(304)
            ->assertHeader('ETag', $etagInicial);

        $consultoCatalogosPesados = collect(DB::getQueryLog())
            ->contains(fn (array $consulta): bool => preg_match(
                '/from\s+[`"]?(especies_validacion|variedades_validacion|calibres_validacion|csg_validacion)[`"]?/i',
                $consulta['query'],
            ) === 1);
        DB::disableQueryLog();
        $this->assertFalse($consultoCatalogosPesados);

        $temporada->increment('version_catalogo');
        $catalogoActualizado = $this->withHeader('If-None-Match', $etagInicial)
            ->getJson('/api/materia-prima/catalogos')
            ->assertOk();
        $etagCatalogoActualizado = $catalogoActualizado->headers->get('ETag');
        $this->assertNotSame($etagInicial, $etagCatalogoActualizado);

        $camara->update(['nombre' => 'Cámara ETag actualizada']);
        $camaraActualizada = $this->withHeader('If-None-Match', $etagCatalogoActualizado)
            ->getJson('/api/materia-prima/catalogos')
            ->assertOk()
            ->assertJsonPath('camaras.0.nombre', 'Cámara ETag actualizada');
        $this->assertNotSame(
            $etagCatalogoActualizado,
            $camaraActualizada->headers->get('ETag'),
        );
    }

    public function test_lotiza_un_segmento_en_varios_lotes_y_completa_hidrocooler_y_camara(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-27 15:00:00'));
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camara = Camara::create([
            'codigo' => 'MP-01',
            'nombre' => 'Cámara materia prima',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $this->actingAs($digitador, 'sanctum')
            ->getJson('/api/materia-prima/segmentos-pendientes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $contexto['segmento_id'])
            ->assertJsonPath('data.0.recepcion.peso_neto', 18000)
            ->assertJsonPath('data.0.recepcion.tipo_envase_calculo_neto', 'bins')
            ->assertJsonPath('data.0.recepcion.peso_neto_por_envase', 375)
            ->assertJsonPath('data.0.envases.0.cantidad_disponible', 48);

        $primerPayload = $this->payloadLote($contexto, [
            'numero_lote' => 'EXP-2026-001-A',
            'cantidad_envases_primarios' => 20,
            'cantidad_envases_secundarios' => 4,
            'kilos_brutos' => 8000,
            'kilos_netos_confirmados' => 7495,
            'requiere_hidrocooler' => true,
        ]);
        $primerLote = $this->postJson('/api/materia-prima/lotes', $primerPayload)
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.pesos.kilos_netos_calculados', 7500)
            ->assertJsonPath('data.pesos.kilos_netos_confirmados', 7495)
            ->assertJsonPath('data.pesos.corregido_por_digitador', true)
            ->json('data');

        $primerLote = $this->postJson(
            "/api/materia-prima/lotes/{$primerLote['id']}/confirmar",
            [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => $primerLote['version'],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente_hidrocooler')
            ->json('data');

        $this->assertDatabaseHas('segmentos_validacion_mp', [
            'id' => $contexto['segmento_id'],
            'estado' => 'lotizacion_parcial',
        ]);

        $segundoPayload = $this->payloadLote($contexto, [
            'numero_lote' => 'EXP-2026-001-B',
            'cantidad_envases_primarios' => 28,
            'cantidad_envases_secundarios' => 6,
            'kilos_brutos' => 11000,
            'kilos_netos_confirmados' => 10500,
            'requiere_hidrocooler' => false,
        ]);
        $segundoLote = $this->postJson('/api/materia-prima/lotes', $segundoPayload)
            ->assertCreated()
            ->json('data');
        $segundoLote = $this->postJson(
            "/api/materia-prima/lotes/{$segundoLote['id']}/confirmar",
            [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => $segundoLote['version'],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente_asignacion')
            ->json('data');

        $this->assertDatabaseHas('segmentos_validacion_mp', [
            'id' => $contexto['segmento_id'],
            'estado' => 'lotizado',
        ]);
        $this->getJson('/api/materia-prima/segmentos-pendientes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $inicio = now()->subMinutes(90);
        $this->postJson(
            "/api/materia-prima/lotes/{$primerLote['id']}/hidrocooler/iniciar",
            [
                'operacion_id' => (string) Str::uuid(),
                'equipo' => 'HIDRO-02',
                'inicio_at' => $inicio->toAtomString(),
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'hidrocooler_en_curso')
            ->assertJsonPath('data.hidrocooler.equipo', 'HIDRO-02')
            ->assertJsonPath('data.hidrocooler.iniciado_por', $digitador->name);

        $this->postJson(
            "/api/materia-prima/lotes/{$primerLote['id']}/hidrocooler/completar",
            [
                'operacion_id' => (string) Str::uuid(),
                'termino_at' => now()->toAtomString(),
                'temperatura_c' => 3.75,
                'observacion' => 'Pulpa dentro del rango.',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente_asignacion')
            ->assertJsonPath('data.hidrocooler.duracion_minutos', 90)
            ->assertJsonPath('data.hidrocooler.temperatura_c', 3.75)
            ->assertJsonPath('data.hidrocooler.observacion', 'Pulpa dentro del rango.')
            ->assertJsonPath('data.hidrocooler.completado_por', $digitador->name);

        foreach ([$primerLote['id'], $segundoLote['id']] as $loteId) {
            $this->postJson("/api/materia-prima/lotes/{$loteId}/asignar-camara", [
                'operacion_id' => (string) Str::uuid(),
                'camara_id' => $camara->id,
                'observacion' => 'Disponible para proceso.',
            ])
                ->assertOk()
                ->assertJsonPath('data.estado', 'asignado_camara')
                ->assertJsonPath('data.asignacion_camara.camara.codigo', 'MP-01');
        }

        $this->getJson('/api/materia-prima/resumen')
            ->assertOk()
            ->assertJsonPath('lotes.borradores', 0)
            ->assertJsonPath('lotes.pendientes_hidrocooler', 0)
            ->assertJsonPath('lotes.pendientes_asignacion', 0)
            ->assertJsonPath('lotes.asignados_camara', 2);
        $this->assertDatabaseCount('lotes_materia_prima', 2);
        $this->assertDatabaseCount('procesos_hidrocooler_materia_prima', 1);
        $this->assertDatabaseCount('asignaciones_camara_lote_materia_prima', 2);
    }

    public function test_controla_identificadores_disponibilidad_y_correccion_supervisada(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-27 15:00:00'));
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create([
            'rol' => RolUsuario::DigitadorMateriaPrima,
            'email' => 'digitador.prueba@estiba.local',
            'password' => 'password123',
        ]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);

        $this->postJson('/api/acceso-oficina', [
            'email' => $digitador->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_consultar_materia_prima', true)
            ->assertJsonPath('usuario.puede_gestionar_lotes_materia_prima', true)
            ->assertJsonPath('usuario.puede_supervisar_lotes_materia_prima', false)
            ->assertJsonPath('usuario.ambito_camaras', 'materia_prima');

        $this->actingAs($digitador, 'sanctum');
        $payload = $this->payloadLote($contexto, [
            'numero_lote' => 'LOTE-CORREGIBLE',
            'cantidad_envases_primarios' => 48,
            'cantidad_envases_secundarios' => 10,
            'kilos_brutos' => 19000,
            'kilos_netos_confirmados' => 18000,
            'requiere_hidrocooler' => false,
        ]);

        $invalido = $payload;
        $invalido['ggn'] = '1234';
        $this->postJson('/api/materia-prima/lotes', $invalido)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ggn');

        $lote = $this->postJson('/api/materia-prima/lotes', $payload)
            ->assertCreated()
            ->json('data');

        $excedente = $this->payloadLote($contexto, [
            'numero_lote' => 'LOTE-SIN-SALDO',
            'cantidad_envases_primarios' => 1,
            'cantidad_envases_secundarios' => 0,
            'envase_secundario' => null,
            'kilos_brutos' => 500,
            'kilos_netos_confirmados' => 375,
            'requiere_hidrocooler' => false,
        ]);
        $this->postJson('/api/materia-prima/lotes', $excedente)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('envases');

        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/materia-prima/lotes/{$lote['id']}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'El número fue informado con antecedentes incorrectos.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulado')
            ->assertJsonPath(
                'data.motivo_anulacion',
                'El número fue informado con antecedentes incorrectos.',
            );

        $this->assertDatabaseHas('segmentos_validacion_mp', [
            'id' => $contexto['segmento_id'],
            'estado' => 'pendiente_lote',
        ]);
        $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', [
                ...$payload,
                'operacion_id' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.numero_lote', 'LOTE-CORREGIBLE')
            ->assertJsonPath('data.estado', 'borrador');

        $this->assertDatabaseCount('lotes_materia_prima', 2);
        $this->assertDatabaseHas('lotes_materia_prima', [
            'id' => $lote['id'],
            'estado' => 'anulado',
            'clave_numero_vigente' => null,
        ]);
    }

    public function test_calibre_no_se_exige_cuartel_es_opcional_y_el_origen_confirmado_se_corrige(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-27 15:00:00'));
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $this->actingAs($digitador, 'sanctum');

        $lote = $this->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
            'numero_lote' => 'LOTE-SIN-CALIBRE',
            'cuartel' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.trazabilidad.calibre_id', null)
            ->assertJsonPath('data.trazabilidad.calibre', null)
            ->assertJsonPath('data.trazabilidad.cuartel', null)
            ->json('data');

        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');

        DB::table('lotes_materia_prima')->where('id', $lote['id'])->update([
            'calibre_validacion_id' => $contexto['calibre_id'],
            'calibre_snapshot' => '28 mm',
            'cuartel' => 'CUARTEL-ANTIGUO',
        ]);
        $operacionId = (string) Str::uuid();
        $payload = [
            'operacion_id' => $operacionId,
            'version_conocida' => $lote['version'],
            'cuartel' => null,
            'retirar_calibre' => true,
            'kilos_brutos' => 1,
        ];

        $corregido = $this->putJson(
            "/api/materia-prima/lotes/{$lote['id']}/corregir-origen",
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.estado', $lote['estado'])
            ->assertJsonPath('data.trazabilidad.calibre_id', null)
            ->assertJsonPath('data.trazabilidad.calibre', null)
            ->assertJsonPath('data.trazabilidad.cuartel', null)
            ->assertJsonPath('data.pesos.kilos_brutos', 19000)
            ->json('data');

        $this->putJson(
            "/api/materia-prima/lotes/{$lote['id']}/corregir-origen",
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.version', $corregido['version']);

        $this->assertDatabaseHas('eventos_lote_materia_prima', [
            'lote_materia_prima_id' => $lote['id'],
            'operacion_id' => $operacionId,
            'tipo' => 'origen_corregido',
        ]);
        $this->assertDatabaseHas('lotes_materia_prima', [
            'id' => $lote['id'],
            'calibre_validacion_id' => null,
            'calibre_snapshot' => null,
            'cuartel' => null,
            'kilos_brutos' => 19000,
        ]);
    }

    public function test_entrega_bins_a_proceso_por_viajes_parciales_sin_sobrepasar_el_saldo(): void
    {
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $camara = Camara::create([
            'codigo' => 'MP-PROCESO',
            'nombre' => 'Cámara fruta a proceso',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
                'numero_lote' => 'LOTE-PROCESO-01',
                'requiere_hidrocooler' => false,
            ]))
            ->assertCreated()
            ->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/asignar-camara", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camara->id,
        ])->assertOk();

        $operacionPrimerViaje = (string) Str::uuid();
        $primerViaje = [
            'operacion_id' => $operacionPrimerViaje,
            'cantidad_envases' => 20,
            'linea_proceso' => 'Línea 2',
            'turno' => 'A',
            'numero_orden' => 'OP-2026-0088',
            'observacion' => 'Primer viaje físico.',
        ];
        $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", $primerViaje)
            ->assertOk()
            ->assertJsonPath('data.estado', 'entrega_parcial_proceso')
            ->assertJsonPath('data.progreso.total', 48)
            ->assertJsonPath('data.progreso.entregados', 20)
            ->assertJsonPath('data.progreso.disponibles', 28)
            ->assertJsonPath('data.entregas.0.linea_proceso', 'Línea 2')
            ->assertJsonPath('data.entregas.0.turno', 'A')
            ->assertJsonPath('data.entregas.0.numero_orden', 'OP-2026-0088');

        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", $primerViaje)
            ->assertOk()
            ->assertJsonPath('data.progreso.entregados', 20);
        $this->assertDatabaseCount('entregas_fruta_proceso', 1);

        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
            ...$primerViaje,
            'cantidad_envases' => 21,
        ])->assertConflict();

        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
            ...$primerViaje,
            'operacion_id' => (string) Str::uuid(),
            'cantidad_envases' => 29,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cantidad_envases');

        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
            ...$primerViaje,
            'operacion_id' => (string) Str::uuid(),
            'cantidad_envases' => 28,
            'turno' => 'B',
            'numero_orden' => 'OP-2026-0089',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregado_proceso')
            ->assertJsonPath('data.progreso.entregados', 48)
            ->assertJsonPath('data.progreso.disponibles', 0);

        $this->getJson('/api/materia-prima/fruta-proceso/resumen')
            ->assertOk()
            ->assertJsonPath('lotes_abiertos', 0)
            ->assertJsonPath('lotes_completados', 1)
            ->assertJsonPath('bins_entregados', 48)
            ->assertJsonPath('bins_disponibles', 0);
    }

    public function test_camarero_anula_solo_su_ultimo_viaje_abierto_y_supervisor_corrige_un_lote_completo(): void
    {
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $otroCamarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $camara = Camara::create([
            'codigo' => 'MP-CORRECCION',
            'nombre' => 'Cámara corrección',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
                'numero_lote' => 'LOTE-CORRECCION-PROCESO',
                'requiere_hidrocooler' => false,
            ]))->assertCreated()->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/asignar-camara", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camara->id,
        ])->assertOk();

        $payload = [
            'operacion_id' => (string) Str::uuid(),
            'cantidad_envases' => 20,
            'linea_proceso' => 'Línea 1',
            'turno' => 'A',
            'numero_orden' => 'ORD-100',
        ];
        $entrega = $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", $payload)
            ->assertOk()
            ->assertJsonPath('data.entregas.0.puede_anular', true)
            ->json('data.entregas.0');

        $this->actingAs($otroCamarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'No corresponde al operador.',
            ])->assertForbidden();

        $anulacion = [
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Cantidad digitada incorrectamente.',
        ];
        $this->actingAs($camarero, 'sanctum')
            ->postJson(
                "/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/anular",
                $anulacion,
            )
            ->assertOk()
            ->assertJsonPath('data.estado', 'asignado_camara')
            ->assertJsonPath('data.progreso.entregados', 0)
            ->assertJsonPath('data.entregas.0.anulado', true);
        $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/anular",
            $anulacion,
        )
            ->assertOk()
            ->assertJsonPath('data.progreso.entregados', 0);

        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
            ...$payload,
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk();
        $primeraVigente = EntregaFrutaProceso::query()
            ->where('lote_materia_prima_id', $lote['id'])
            ->whereNull('anulado_at')
            ->firstOrFail();
        $this->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
            ...$payload,
            'operacion_id' => (string) Str::uuid(),
            'cantidad_envases' => 28,
        ])->assertOk()->assertJsonPath('data.estado', 'entregado_proceso');

        $this->postJson("/api/materia-prima/fruta-proceso/entregas/{$primeraVigente->id}/anular", [
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Intento después del cierre.',
        ])->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/entregas/{$primeraVigente->id}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Corrección supervisada por error documental.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'entrega_parcial_proceso')
            ->assertJsonPath('data.progreso.entregados', 28)
            ->assertJsonPath('data.progreso.disponibles', 20);
    }

    public function test_registra_retorno_de_packing_crea_sublotes_y_los_ubica_en_camara(): void
    {
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $camaraOrigen = Camara::create([
            'codigo' => 'MP-ORIGEN-PACK',
            'nombre' => 'Cámara origen Packing',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);
        $camaraRetorno = Camara::create([
            'codigo' => 'MP-RETORNO-PACK',
            'nombre' => 'Cámara retorno Packing',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
                'numero_lote' => 'LOTE-RETORNO-PACKING',
                'requiere_hidrocooler' => false,
            ]))->assertCreated()->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/asignar-camara", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camaraOrigen->id,
        ])->assertOk();

        $entrega = $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
                'operacion_id' => (string) Str::uuid(),
                'cantidad_envases' => 20,
                'kilos_enviados' => 7500,
                'linea_proceso' => 'Línea 2',
                'turno' => 'A',
                'numero_orden' => 'OP-RET-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.entregas.0.kilos_enviados', 7500)
            ->json('data.entregas.0');

        $catalogos = $this->getJson('/api/materia-prima/fruta-proceso/catalogos')
            ->assertOk()
            ->assertJsonFragment(['codigo' => 'MP-ORIGEN-PACK'])
            ->assertJsonFragment(['codigo' => 'MP-RETORNO-PACK'])
            ->assertJsonCount(4, 'tipos_resultado')
            ->json();
        $tipos = collect($catalogos['tipos_resultado'])->keyBy('codigo');

        $retornoParcial = $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/retornos",
            [
                'operacion_id' => (string) Str::uuid(),
                'cierra_entrega' => false,
                'resultados' => [[
                    'tipo_resultado_packing_id' => $tipos['precalibre']['id'],
                    'cantidad_bins' => 1,
                    'kilos_netos' => 350,
                ]],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.entregas.0.retorno.estado', 'parcial')
            ->assertJsonPath('data.entregas.0.retorno.bins_retornados', 1)
            ->json('data.entregas.0.retorno.movimientos.0');
        $this->postJson("/api/materia-prima/fruta-proceso/retornos/{$retornoParcial['id']}/anular", [
            'operacion_id' => (string) Str::uuid(),
            'motivo' => 'Packing corrigió la clasificación informada.',
        ])
            ->assertOk()
            ->assertJsonPath('data.entregas.0.retorno.estado', 'pendiente')
            ->assertJsonPath('data.entregas.0.retorno.bins_retornados', 0)
            ->assertJsonPath('data.entregas.0.retorno.movimientos.0.anulado', true);

        $operacionRetorno = (string) Str::uuid();
        $retornoPayload = [
            'operacion_id' => $operacionRetorno,
            'cierra_entrega' => true,
            'observacion' => 'Packing cerró el viaje físico.',
            'resultados' => [
                [
                    'tipo_resultado_packing_id' => $tipos['precalibre']['id'],
                    'cantidad_bins' => 12,
                    'kilos_netos' => 4500,
                ],
                [
                    'tipo_resultado_packing_id' => $tipos['comercial']['id'],
                    'cantidad_bins' => 6,
                    'kilos_netos' => 2200,
                ],
                [
                    'tipo_resultado_packing_id' => $tipos['descarte']['id'],
                    'cantidad_bins' => 2,
                    'kilos_netos' => 500,
                ],
            ],
        ];

        $respuesta = $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/retornos",
            $retornoPayload,
        )
            ->assertOk()
            ->assertJsonPath('data.entregas.0.retorno.estado', 'completado')
            ->assertJsonPath('data.entregas.0.retorno.bins_retornados', 20)
            ->assertJsonPath('data.entregas.0.retorno.kilos_recuperados', 7200)
            ->assertJsonPath('data.entregas.0.retorno.merma_kilos', 300)
            ->assertJsonPath('data.entregas.0.retorno.puede_registrar', false)
            ->assertJsonPath('data.entregas.0.puede_anular', false)
            ->assertJsonCount(3, 'data.entregas.0.retorno.movimientos.0.resultados')
            ->json('data.entregas.0.retorno.movimientos.0');

        $this->assertMatchesRegularExpression('/^RP-\d{6}$/', $respuesta['numero']);
        $resultadoPrecalibre = collect($respuesta['resultados'])->first(
            fn (array $resultado): bool => $resultado['tipo']['codigo'] === 'precalibre',
        );
        $this->assertNotNull($resultadoPrecalibre);
        $this->assertMatchesRegularExpression(
            '/^PC-\d{6}$/',
            $resultadoPrecalibre['numero_sublote'],
        );
        $this->assertDatabaseCount('retornos_packing', 2);
        $this->assertDatabaseCount('retorno_packing_entregas', 2);
        $this->assertDatabaseHas('retorno_packing_entregas', [
            'retorno_packing_id' => $respuesta['id'],
            'entrega_fruta_proceso_id' => $entrega['id'],
            'cierra_entrega' => true,
        ]);
        $this->assertDatabaseCount('sublotes_retorno_packing', 4);

        $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$entrega['id']}/retornos",
            $retornoPayload,
        )
            ->assertOk()
            ->assertJsonCount(2, 'data.entregas.0.retorno.movimientos');
        $this->assertDatabaseCount('retornos_packing', 2);

        $sublote = $respuesta['resultados'][0];
        $this->postJson("/api/materia-prima/fruta-proceso/sublotes/{$sublote['id']}/ubicar", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camaraRetorno->id,
            'observacion' => 'Retorno ubicado por el camarero.',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.entregas.0.retorno.movimientos.0.resultados.0.estado',
                'ubicado_camara',
            )
            ->assertJsonPath(
                'data.entregas.0.retorno.movimientos.0.resultados.0.camara.codigo',
                'MP-RETORNO-PACK',
            );

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/retornos/{$respuesta['id']}/anular", [
                'operacion_id' => (string) Str::uuid(),
                'motivo' => 'Intento posterior a la ubicación.',
            ])->assertForbidden();

        $this->getJson('/api/materia-prima/fruta-proceso/resumen')
            ->assertOk()
            ->assertJsonPath('entregas_pendientes_retorno', 0)
            ->assertJsonPath('bins_retornados', 20)
            ->assertJsonPath('kilos_recuperados', 7200)
            ->assertJsonPath('sublotes_pendientes_ubicacion', 2);
    }

    public function test_retorno_multiorigen_cierra_cada_viaje_por_separado_y_no_duplica_el_resumen(): void
    {
        $contexto = $this->prepararRecepcionValidada();
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);
        $camara = Camara::create([
            'codigo' => 'MP-MULTIORIGEN',
            'nombre' => 'Cámara multiorigen',
            'tipo' => 'almacenaje',
            'contenido' => ContenidoCamara::MateriaPrima,
            'cantidad_bandas' => 1,
            'posiciones_por_banda' => 1,
            'cantidad_niveles' => 1,
        ]);

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', $this->payloadLote($contexto, [
                'numero_lote' => 'LOTE-MULTIORIGEN',
                'requiere_hidrocooler' => false,
            ]))->assertCreated()->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])->assertOk()->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/asignar-camara", [
            'operacion_id' => (string) Str::uuid(),
            'camara_id' => $camara->id,
        ])->assertOk();

        $primera = $this->actingAs($camarero, 'sanctum')
            ->postJson("/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas", [
                'operacion_id' => (string) Str::uuid(),
                'cantidad_envases' => 20,
                'kilos_enviados' => 7500,
                'linea_proceso' => 'Línea 1',
                'turno' => 'A',
                'numero_orden' => 'OP-MULTI-001',
            ])->assertOk()->json('data.entregas.0');
        $segunda = $this->postJson(
            "/api/materia-prima/fruta-proceso/lotes/{$lote['id']}/entregas",
            [
                'operacion_id' => (string) Str::uuid(),
                'cantidad_envases' => 10,
                'kilos_enviados' => 3750,
                'linea_proceso' => 'Línea 2',
                'turno' => 'A',
                'numero_orden' => 'OP-MULTI-002',
            ],
        )->assertOk()->json('data.entregas.0');
        $tipo = TipoResultadoPacking::query()->where('codigo', 'comercial')->firstOrFail();

        $respuesta = $this->postJson(
            "/api/materia-prima/fruta-proceso/entregas/{$primera['id']}/retornos",
            [
                'operacion_id' => (string) Str::uuid(),
                'entregas' => [
                    [
                        'entrega_fruta_proceso_id' => $primera['id'],
                        'cierra_entrega' => true,
                    ],
                    [
                        'entrega_fruta_proceso_id' => $segunda['id'],
                        'cierra_entrega' => false,
                    ],
                ],
                'resultados' => [[
                    'tipo_resultado_packing_id' => $tipo->id,
                    'cantidad_bins' => 8,
                    'kilos_netos' => 3000,
                ]],
            ],
        )->assertOk()->json('data');

        $primeraActual = collect($respuesta['entregas'])->firstWhere('id', $primera['id']);
        $segundaActual = collect($respuesta['entregas'])->firstWhere('id', $segunda['id']);
        $this->assertSame('completado', $primeraActual['retorno']['estado']);
        $this->assertSame('parcial', $segundaActual['retorno']['estado']);
        $this->assertCount(2, $primeraActual['retorno']['movimientos'][0]['origenes']);
        $this->assertDatabaseCount('retorno_packing_entregas', 2);
        $this->assertDatabaseHas('retorno_packing_entregas', [
            'entrega_fruta_proceso_id' => $primera['id'],
            'cierra_entrega' => true,
        ]);
        $this->assertDatabaseHas('retorno_packing_entregas', [
            'entrega_fruta_proceso_id' => $segunda['id'],
            'cierra_entrega' => false,
        ]);

        $this->getJson('/api/materia-prima/fruta-proceso/resumen')
            ->assertOk()
            ->assertJsonPath('entregas_pendientes_retorno', 1)
            ->assertJsonPath('retornos_registrados', 1)
            ->assertJsonPath('bins_retornados', 8)
            ->assertJsonPath('kilos_recuperados', 3000)
            ->assertJsonPath('desglose_resultados.0.tipo.codigo', 'comercial')
            ->assertJsonPath('desglose_resultados.0.bins', 8);
    }

    /** @return array<string, mixed> */
    private function prepararRecepcionValidada(): array
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $cliente = Cliente::create([
            'codigo' => 'EXP-MP',
            'nombre' => 'Exportadora Materia Prima',
            'activo' => true,
        ]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $recepcion = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', [
                'operacion_id' => (string) Str::uuid(),
                'temporada_id' => $temporada->id,
                'cliente_id' => $cliente->id,
                'tipo_recepcion' => 'fruta_con_envases',
                'tipo_servicio' => 'proceso',
                'envases' => [
                    ['tipo_envase' => 'bins', 'cantidad' => 48],
                    ['tipo_envase' => 'totes', 'cantidad' => 10],
                ],
                'numero_guia_despacho' => 'GD-MP-100',
                'patente_camion' => 'ABCD12',
                'rut_conductor' => '12.345.678-5',
                'nombre_conductor' => 'Transportista MP',
                'peso_bruto' => 28000,
            ])
            ->assertCreated()
            ->json('data');
        $this->postJson("/api/romana/recepciones/{$recepcion['id']}/confirmar-ingreso", [
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk();
        $this->postJson("/api/romana/recepciones/{$recepcion['id']}/cerrar", [
            'operacion_id' => (string) Str::uuid(),
            'peso_tara' => 10000,
            'tipo_envase_calculo_neto' => 'bins',
        ])
            ->assertOk()
            ->assertJsonPath('data.peso_neto', 18000)
            ->assertJsonPath('data.cantidad_envase_calculo_neto', 48)
            ->assertJsonPath('data.peso_neto_por_envase', 375);

        $especie = EspecieValidacion::create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza',
            'activo' => true,
        ]);
        $variedad = VariedadValidacion::create([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'Santina',
            'activo' => true,
        ]);
        $calibre = CalibreValidacion::create([
            'especie_validacion_id' => $especie->id,
            'nombre' => '28 mm',
            'activo' => true,
        ]);
        $csg = CsgValidacion::create([
            'temporada_id' => $temporada->id,
            'codigo' => '12345678',
            'predio' => 'Fundo El Maitén',
            'activo' => true,
        ]);

        $validacion = $this->actingAs($validador, 'sanctum')
            ->postJson("/api/validacion-mp/recepciones/{$recepcion['id']}/tomar", [
                'operacion_id' => (string) Str::uuid(),
            ])
            ->assertOk()
            ->json('data');
        $segmentoId = $this->postJson(
            "/api/validacion-mp/validaciones/{$validacion['id']}/confirmar",
            [
                'operacion_id' => (string) Str::uuid(),
                'envases' => [
                    ['tipo_envase' => 'bins', 'cantidad_validada' => 48],
                    ['tipo_envase' => 'totes', 'cantidad_validada' => 10],
                ],
                'tarjas_verificadas' => true,
                'requiere_segregacion' => false,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'validada')
            ->json('data.segmentos.0.id');

        return [
            'temporada' => $temporada,
            'cliente' => $cliente,
            'recepcion_id' => $recepcion['id'],
            'segmento_id' => $segmentoId,
            'csg_id' => $csg->id,
            'especie_id' => $especie->id,
            'variedad_id' => $variedad->id,
            'calibre_id' => $calibre->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<string, mixed>  $reemplazos
     * @return array<string, mixed>
     */
    private function payloadLote(array $contexto, array $reemplazos = []): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'segmento_validacion_mp_id' => $contexto['segmento_id'],
            'numero_lote' => 'EXP-2026-001',
            'csg_validacion_id' => $contexto['csg_id'],
            'sdp' => '987654321',
            'ggn' => '1234567890123',
            'fecha_cosecha' => '2026-07-26',
            'predio' => 'Fundo El Maitén',
            'especie_validacion_id' => $contexto['especie_id'],
            'variedad_validacion_id' => $contexto['variedad_id'],
            'cuartel' => 'C-12',
            'tipo_producto' => 'materia_prima',
            'envase_primario' => 'bins',
            'envase_secundario' => 'totes',
            'cantidad_envases_primarios' => 48,
            'cantidad_envases_secundarios' => 10,
            'kilos_brutos' => 19000,
            'kilos_netos_confirmados' => 18000,
            'requiere_hidrocooler' => false,
            'observacion' => 'Antecedentes informados por exportadora.',
            ...$reemplazos,
        ];
    }
}
