<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\EventoRecepcionRomana;
use App\Models\Temporada;
use App\Models\TemporadaMaterial;
use App\Models\User;
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
            ->assertJsonPath('data.numero_recepcion', null)
            ->assertJsonPath('data.cliente.nombre', 'Exportadora Los Andes')
            ->assertJsonPath('data.peso_bruto', 28540)
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

    public function test_unifica_clientes_de_validacion_y_materiales_para_los_nuevos_flujos(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $temporadaValidacion = Temporada::create([
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
        ]);
        $temporadaMaterial = TemporadaMaterial::create([
            'codigo' => 'MAT-2026',
            'nombre' => 'Materiales 2026',
            'activa' => true,
        ]);

        $clienteId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/validacion/clientes', [
                'temporada_id' => $temporadaValidacion->id,
                'nombre' => 'Exportadora Transversal',
                'codigo_externo' => 'CLI-TRANS-01',
                'activo' => true,
            ])
            ->assertCreated()
            ->json('data.cliente_id');

        $this->postJson('/api/administracion/materiales/clientes', [
            'temporada_material_id' => $temporadaMaterial->id,
            'codigo' => 'TRANSVERSAL',
            'nombre' => 'Exportadora Transversal',
            'codigo_externo' => 'CLI-TRANS-01',
            'activo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cliente_id', $clienteId);

        $this->assertSame(1, Cliente::query()->where('codigo_externo', 'CLI-TRANS-01')->count());
        $this->getJson('/api/romana/catalogos')
            ->assertOk()
            ->assertJsonPath('clientes.0.id', $clienteId)
            ->assertJsonPath('clientes.0.presente_en_validacion', true)
            ->assertJsonPath('clientes.0.presente_en_materiales', true);
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
            'cliente_id' => $cliente->id,
            'tipo_servicio' => 'prefrio',
            'cantidad_envases_declarados' => 48,
            'tipo_envase_declarado' => 'bins',
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
