<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\EstadoValidacionMp;
use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\EventoRecepcionRomana;
use App\Models\RecepcionRomana;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecepcionRomanaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_completa_el_pesaje_en_dos_tiempos_y_emite_el_aviso_de_recibo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 10:45:00'));
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $cliente = $this->cliente();
        $datos = $this->datosIngreso($cliente);

        $creada = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertCreated()
            ->assertJsonPath('data.estado', EstadoRecepcionRomana::EnBasculaIngreso->value)
            ->assertJsonPath('data.numero_recepcion', 'REC-2607-0001')
            ->assertJsonPath('data.temporada.id', $datos['temporada_id'])
            ->assertJsonPath('data.cliente.nombre', 'Exportadora Los Andes')
            ->assertJsonPath('data.peso_bruto', 28540)
            ->assertJsonPath('data.envases.0.tipo_envase', 'bins')
            ->assertJsonPath('data.envases.0.cantidad_declarada', 48)
            ->json('data');

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertCreated()
            ->assertJsonPath('data.id', $creada['id']);

        $operacionConfirmacion = (string) Str::uuid();
        $this->postJson('/api/romana/recepciones/'.$creada['id'].'/confirmar-ingreso', [
            'operacion_id' => $operacionConfirmacion,
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoRecepcionRomana::EnBasculaSalida->value)
            ->assertJsonPath('data.puede_cerrar', true);

        $this->postJson('/api/romana/recepciones/'.$creada['id'].'/confirmar-ingreso', [
            'operacion_id' => $operacionConfirmacion,
        ])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-07-21 14:10:00'));
        $cerrada = $this->postJson('/api/romana/recepciones/'.$creada['id'].'/cerrar', [
            'operacion_id' => (string) Str::uuid(),
            'peso_tara' => 10540,
            'observacion' => 'Sellos y guía verificados.',
        ])
            ->assertOk()
            ->assertJsonPath('data.numero_recepcion', 'REC-2607-0001')
            ->assertJsonPath('data.estado', EstadoRecepcionRomana::Cerrado->value)
            ->assertJsonPath('data.peso_tara', 10540)
            ->assertJsonPath('data.peso_neto', 18000)
            ->assertJsonPath('data.aviso_recibo_disponible', true)
            ->json('data');

        $this->assertDatabaseHas('recepciones_romana', [
            'id' => $creada['id'],
            'numero_recepcion' => 'REC-2607-0001',
            'peso_neto' => 18000,
            'estado' => EstadoRecepcionRomana::Cerrado->value,
        ]);
        $this->assertSame(3, EventoRecepcionRomana::query()->count());
        $this->assertDatabaseCount('folios', 0);
        $this->assertDatabaseCount('validaciones_pallet', 0);
        $this->assertDatabaseCount('procesos_prefrio', 0);

        $cliente->update(['nombre' => 'Nombre modificado posteriormente']);
        $this->getJson('/api/romana/recepciones/'.$creada['id'])
            ->assertOk()
            ->assertJsonPath('data.cliente.nombre', 'Exportadora Los Andes')
            ->assertJsonCount(3, 'data.eventos');

        $pdf = $this->get('/api/romana/recepciones/'.$cerrada['id'].'/aviso-recibo')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="aviso-recibo-rec-2607-0001.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdf->getContent());

        $gerencia = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $this->actingAs($gerencia, 'sanctum')
            ->getJson('/api/gerencia/resumen')
            ->assertOk()
            ->assertJsonPath('data.romana.cerradas_hoy', 1)
            ->assertJsonPath('data.romana.peso_neto_hoy', 18000)
            ->assertJsonPath('data.romana.envases_hoy', 48)
            ->assertJsonPath('data.romana.clientes_hoy', 1)
            ->assertJsonPath('data.romana.tendencia_diaria.6.recepciones', 1)
            ->assertJsonPath('data.romana.tendencia_diaria.6.peso_neto', 18000);
    }

    public function test_bloquea_destare_invalido_duplicados_y_edicion_despues_de_confirmar(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $cliente = $this->cliente();
        $datos = $this->datosIngreso($cliente);
        $id = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertCreated()
            ->json('data.id');

        $edicionValida = $datos;
        $edicionValida['operacion_id'] = (string) Str::uuid();
        $edicionValida['peso_bruto'] = 29000;
        $this->putJson('/api/romana/recepciones/'.$id, $edicionValida)
            ->assertOk()
            ->assertJsonPath('data.peso_bruto', 29000)
            ->assertJsonPath('data.version', 2);

        $this->postJson('/api/romana/recepciones/'.$id.'/cerrar', [
            'operacion_id' => (string) Str::uuid(),
            'peso_tara' => 10000,
        ])->assertConflict()->assertJsonPath('codigo', 'conflicto_operacional');

        $duplicado = $this->datosIngreso($cliente);
        $duplicado['operacion_id'] = (string) Str::uuid();
        $this->postJson('/api/romana/recepciones', $duplicado)
            ->assertConflict()
            ->assertJsonPath('message', 'La guía de despacho ya fue registrada para este cliente.');

        $this->postJson('/api/romana/recepciones/'.$id.'/confirmar-ingreso', [
            'operacion_id' => (string) Str::uuid(),
        ])->assertOk();

        $edicion = $edicionValida;
        $edicion['operacion_id'] = (string) Str::uuid();
        $edicion['peso_bruto'] = 30000;
        $this->putJson('/api/romana/recepciones/'.$id, $edicion)
            ->assertConflict()
            ->assertJsonPath('message', 'La recepción ya confirmó su ingreso y sus antecedentes no pueden editarse.');

        $this->postJson('/api/romana/recepciones/'.$id.'/cerrar', [
            'operacion_id' => (string) Str::uuid(),
            'peso_tara' => 29000,
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'La tara debe ser menor que el peso bruto registrado.');

        $this->assertDatabaseHas('recepciones_romana', [
            'id' => $id,
            'estado' => EstadoRecepcionRomana::EnBasculaSalida->value,
            'peso_tara' => null,
        ]);
    }

    public function test_bloquea_edicion_en_romana_cuando_validacion_mp_ya_tomo_la_recepcion(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $datos = $this->datosIngreso($this->cliente());
        $recepcionId = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('recepciones_romana', [
            'id' => $recepcionId,
            'estado_validacion_mp' => EstadoValidacionMp::Pendiente->value,
        ]);

        RecepcionRomana::query()->findOrFail($recepcionId)->update([
            'estado_validacion_mp' => EstadoValidacionMp::EnCurso,
        ]);
        $edicion = $datos;
        $edicion['operacion_id'] = (string) Str::uuid();
        $edicion['peso_bruto'] = 30000;

        $this->putJson('/api/romana/recepciones/'.$recepcionId, $edicion)
            ->assertConflict()
            ->assertJsonPath('message', 'La recepción ya fue tomada por Validación MP y sus antecedentes no pueden editarse.');
    }

    public function test_separa_consulta_de_operacion_y_expone_capacidades_en_el_acceso(): void
    {
        $cliente = $this->cliente();
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $operador = User::factory()->create([
            'rol' => RolUsuario::OperadorRomana,
            'email' => 'romana@estiba.local',
            'password' => 'password123',
        ]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroFrio]);

        $this->actingAs($consulta, 'sanctum')->getJson('/api/romana/recepciones')->assertOk();
        $this->postJson('/api/romana/recepciones', $this->datosIngreso($cliente))->assertForbidden();
        $this->actingAs($camarero, 'sanctum')->getJson('/api/romana/recepciones')->assertForbidden();

        $this->postJson('/api/acceso-oficina', [
            'email' => $operador->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('usuario.puede_consultar_romana', true)
            ->assertJsonPath('usuario.puede_operar_romana', true)
            ->assertJsonPath('usuario.ambito_camaras', 'ninguno');
    }

    public function test_requiere_un_cliente_operacional_activo_y_un_rut_valido(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $cliente = $this->cliente(false);
        $datos = $this->datosIngreso($cliente);

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cliente_id');

        $cliente->update(['activo' => true]);
        $datos['rut_conductor'] = '12.345.678-9';
        $this->postJson('/api/romana/recepciones', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rut_conductor');
    }

    public function test_comparte_temporada_y_cliente_sin_unir_los_flujos_operacionales(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $temporadaId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/temporadas', [
                'codigo' => '2026-2027',
                'nombre' => 'Temporada 2026-2027',
                'activa' => true,
            ])
            ->assertCreated()
            ->json('data.id');
        $temporadaMaterialId = Temporada::query()
            ->findOrFail($temporadaId)
            ->configuracionMaterial()
            ->firstOrFail()
            ->id;

        $clienteId = $this->postJson('/api/administracion/validacion/clientes', [
            'temporada_id' => $temporadaId,
            'nombre' => 'Exportadora Transversal',
            'codigo_externo' => 'CLI-TRANS-01',
            'activo' => true,
        ])
            ->assertCreated()
            ->json('data.cliente_id');

        $this->postJson('/api/administracion/materiales/clientes', [
            'temporada_material_id' => $temporadaMaterialId,
            'codigo' => 'TRANSVERSAL',
            'nombre' => 'Exportadora Transversal',
            'codigo_externo' => 'CLI-TRANS-01',
            'activo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cliente_id', $clienteId);

        $this->assertSame(1, Cliente::query()->where('codigo_externo', 'CLI-TRANS-01')->count());
        $this->assertSame(1, Temporada::query()->where('codigo', '2026-2027')->count());
        $this->assertSame(1, Temporada::query()->where('activa', true)->count());
        $this->getJson('/api/romana/catalogos')
            ->assertOk()
            ->assertJsonPath('temporadas.0.id', $temporadaId)
            ->assertJsonPath('clientes.0.id', $clienteId)
            ->assertJsonPath('clientes.0.presente_en_validacion', true)
            ->assertJsonPath('clientes.0.presente_en_materiales', true);
    }

    public function test_romana_muestra_la_temporada_activa_por_defecto_y_conserva_consulta_historica_explicita(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $cliente = $this->cliente();
        $temporadaAnterior = Temporada::query()->where('activa', true)->firstOrFail();

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $this->datosIngreso($cliente))
            ->assertCreated();

        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'ROM-NUEVA',
            'nombre' => 'Temporada nueva de romana',
            'activa' => true,
        ], usuarioId: $operador->id);

        $this->getJson('/api/romana/recepciones')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->getJson("/api/romana/recepciones?temporada_id={$temporadaAnterior->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.temporada.id', $temporadaAnterior->id);
    }

    public function test_numera_y_notifica_una_recepcion_solo_de_envases_con_detalle_separado(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-21 16:32:15'));
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $datos = $this->datosIngreso($this->cliente());
        $datos['tipo_recepcion'] = 'solo_envases';
        $datos['concepto_envases'] = 'arriendo';
        $datos['tipo_servicio'] = null;
        $datos['envases'] = [
            ['tipo_envase' => 'bins', 'cantidad' => 120],
            ['tipo_envase' => 'esponjas', 'cantidad' => 800],
        ];

        $recepcion = $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $datos)
            ->assertCreated()
            ->assertJsonPath('data.numero_recepcion', 'REC-2607-0001')
            ->assertJsonPath('data.tipo_recepcion', 'solo_envases')
            ->assertJsonPath('data.concepto_envases', 'arriendo')
            ->assertJsonPath('data.estado_validacion_mp', 'pendiente')
            ->assertJsonCount(2, 'data.envases')
            ->json('data');

        $this->assertDatabaseHas('detalles_envases_recepcion_romana', [
            'recepcion_romana_id' => $recepcion['id'],
            'tipo_envase' => 'esponjas',
            'cantidad_declarada' => 800,
            'cantidad_validada' => null,
        ]);
        $this->actingAs($validador, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'recepcion_romana_creada')
            ->assertJsonPath('data.0.recepcion_romana.numero_recepcion', 'REC-2607-0001')
            ->assertJsonPath('data.0.datos.ingreso_at', '2026-07-21T16:32:15+00:00');

        $this->actingAs($operador, 'sanctum')
            ->getJson('/api/envases/cuenta-corriente/movimientos')
            ->assertOk()
            ->assertJsonPath('resumen.lineas_pendientes_validacion', 2)
            ->assertJsonPath('pendientes.0.numero_recepcion', 'REC-2607-0001');
    }

    public function test_oculta_notificaciones_de_recepciones_de_temporadas_anteriores(): void
    {
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/romana/recepciones', $this->datosIngreso($this->cliente()))
            ->assertCreated();
        $this->actingAs($validador, 'sanctum')
            ->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'NOT-NUEVA',
            'nombre' => 'Temporada nueva de notificaciones',
            'activa' => true,
        ], usuarioId: $operador->id);

        $this->getJson('/api/notificaciones-operacionales')
            ->assertOk()
            ->assertJsonPath('resumen.no_leidas', 0)
            ->assertJsonCount(0, 'data');
    }

    private function cliente(bool $activo = true): Cliente
    {
        return Cliente::create([
            'codigo' => 'ELA-01',
            'nombre' => 'Exportadora Los Andes',
            'codigo_externo' => 'ELA-01',
            'activo' => $activo,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosIngreso(Cliente $cliente): array
    {
        return [
            'operacion_id' => (string) Str::uuid(),
            'temporada_id' => Temporada::query()->where('activa', true)->firstOrFail()->id,
            'cliente_id' => $cliente->id,
            'tipo_recepcion' => 'fruta_con_envases',
            'concepto_envases' => null,
            'tipo_servicio' => 'prefrio',
            'envases' => [
                ['tipo_envase' => 'bins', 'cantidad' => 48],
            ],
            'numero_guia_despacho' => 'GD-77881',
            'patente_camion' => 'ABCD12',
            'patente_carro' => 'WXYZ34',
            'rut_conductor' => '12.345.678-5',
            'nombre_conductor' => 'María González',
            'peso_bruto' => 28540,
            'observacion' => 'Carga sellada en origen.',
        ];
    }
}
