<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoLoteMateriaPrima;
use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\Folio;
use App\Models\LoteMateriaPrima;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class TrazabilidadLotesApiTest extends TestCase
{
    use RefreshDatabase;

    private string $temporadaId;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporadaId = (string) Str::uuid();
        DB::table('temporadas')->insert([
            'id' => $this->temporadaId,
            'codigo' => 'TRZ-2026',
            'nombre' => 'Temporada trazabilidad',
            'activa' => false,
            'version_catalogo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->administrador = User::factory()->create(['rol' => RolUsuario::Administrador, 'activo' => true]);
    }

    public function test_no_afirma_la_recepcion_de_un_lote_de_otro_cliente_con_el_mismo_numero(): void
    {
        $norte = $this->cliente('NORTE');
        $sur = $this->cliente('SUR');
        $this->lote($sur, 'L-900');
        $this->folio('TRZ-OTRO-CLIENTE', $this->origen($norte), 'L-900');

        $this->assertDatabaseHas('trazabilidad_folio_origenes', [
            'numero_lote_materia_prima' => 'L-900',
            'cliente_id' => $norte->id,
            'lote_materia_prima_id' => null,
        ]);
        $this->actingAs($this->administrador, 'sanctum')
            ->getJson('/api/consultas/trazabilidad?q=L-900')
            ->assertOk()
            ->assertJsonPath('data.resumen.lineas_sin_lote_verificado', 1)
            ->assertJsonPath('data.folios.0.lineas.0.lote_verificado', false)
            ->assertJsonPath('data.folios.0.lineas.0.recepcion', null);
    }

    public function test_concilia_el_vinculo_cuando_el_lote_se_digita_despues_y_lo_retira_al_anularlo(): void
    {
        $norte = $this->cliente('NORTE');
        $this->folio('TRZ-ANTES-DEL-LOTE', $this->origen($norte), 'l-901 ');
        $this->assertDatabaseHas('trazabilidad_folio_origenes', [
            'numero_lote_materia_prima' => 'L-901',
            'lote_materia_prima_id' => null,
        ]);

        $lote = $this->lote($norte, 'L-901');
        $this->assertDatabaseHas('trazabilidad_folio_origenes', [
            'numero_lote_materia_prima' => 'L-901',
            'lote_materia_prima_id' => $lote->id,
        ]);

        Schema::disableForeignKeyConstraints();
        $lote->update(['estado' => EstadoLoteMateriaPrima::Anulado->value]);
        Schema::enableForeignKeyConstraints();
        $this->assertDatabaseHas('trazabilidad_folio_origenes', [
            'numero_lote_materia_prima' => 'L-901',
            'lote_materia_prima_id' => null,
        ]);
    }

    public function test_el_resumen_cuenta_todos_los_folios_y_el_excel_los_incluye_sin_limite(): void
    {
        $origen = $this->origen($this->cliente('NORTE'));
        foreach (range(1, 60) as $numero) {
            $this->folio(sprintf('TRZ-%03d', $numero), $origen, 'L-77', 'P-5');
        }

        $this->actingAs($this->administrador, 'sanctum')
            ->getJson('/api/consultas/trazabilidad?q=p-5')
            ->assertOk()
            ->assertJsonPath('data.resumen.folios', 60)
            ->assertJsonPath('data.resumen.cajas_coincidentes', 600)
            ->assertJsonPath('data.paginacion.paginas', 2)
            ->assertJsonCount(50, 'data.folios')
            ->assertJsonPath('data.folios.0.numero', 'TRZ-001');
        $this->getJson('/api/consultas/trazabilidad?q=P-5&pagina=2')
            ->assertOk()
            ->assertJsonPath('data.paginacion.pagina', 2)
            ->assertJsonCount(10, 'data.folios')
            ->assertJsonPath('data.folios.9.numero', 'TRZ-060');

        $respuesta = $this->get('/api/consultas/trazabilidad/exportar?q=P-5')->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        foreach (range(1, 60) as $numero) {
            $this->assertStringContainsString(sprintf('TRZ-%03d', $numero), $hoja);
        }
    }

    public function test_el_excel_de_trazabilidad_requiere_acceso_a_consultas(): void
    {
        $romana = User::factory()->create(['rol' => RolUsuario::OperadorRomana, 'activo' => true]);

        $this->actingAs($romana, 'sanctum')
            ->get('/api/consultas/trazabilidad/exportar?q=P-5')
            ->assertForbidden();
    }

    private function cliente(string $codigo): Cliente
    {
        return Cliente::query()->create([
            'codigo' => $codigo,
            'nombre' => 'Exportadora '.$codigo,
            'activo' => true,
        ]);
    }

    private function origen(Cliente $cliente): string
    {
        $clienteValidacion = (string) Str::uuid();
        $origen = (string) Str::uuid();
        DB::table('clientes_validacion')->insert([
            'id' => $clienteValidacion,
            'temporada_id' => $this->temporadaId,
            'cliente_id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('origenes_validacion')->insert([
            'id' => $origen,
            'temporada_id' => $this->temporadaId,
            'cliente_validacion_id' => $clienteValidacion,
            'cliente' => $cliente->codigo,
            'marca' => 'ATLAS',
            'csg' => '105410',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $origen;
    }

    private function folio(string $numero, string $origenId, string $lote, ?string $proceso = null): Folio
    {
        return Folio::query()->create([
            'temporada_id' => $this->temporadaId,
            'numero_folio' => $numero,
            'tipo_bulto' => 'pallet',
            'estado_operacional' => 'disponible',
            'fecha_ingreso' => now(),
            'activo' => true,
            'datos_externos' => ['composicion' => [array_filter([
                'origen_validacion_id' => $origenId,
                'csg' => '105410',
                'cantidad_cajas' => 10,
                'lote_materia_prima' => $lote,
                'proceso_packing' => $proceso,
            ])]],
        ]);
    }

    /** El lote se crea sin su recepción ni catálogo MP: esta prueba solo necesita número y cliente. */
    private function lote(Cliente $cliente, string $numero): LoteMateriaPrima
    {
        Schema::disableForeignKeyConstraints();

        try {
            return LoteMateriaPrima::query()->create([
                'operacion_id' => (string) Str::uuid(),
                'payload_hash' => str_repeat('a', 64),
                'segmento_validacion_mp_id' => (string) Str::uuid(),
                'recepcion_romana_id' => (string) Str::uuid(),
                'temporada_id' => $this->temporadaId,
                'cliente_id' => $cliente->id,
                'numero_lote' => $numero,
                'estado' => EstadoLoteMateriaPrima::DisponibleProceso->value,
                'csg_validacion_id' => (string) Str::uuid(),
                'csg_snapshot' => '105410',
                'sdp' => '1',
                'ggn' => '1',
                'fecha_cosecha' => now()->toDateString(),
                'predio' => 'Predio',
                'especie_validacion_id' => (string) Str::uuid(),
                'especie_snapshot' => 'Cereza',
                'variedad_validacion_id' => (string) Str::uuid(),
                'variedad_snapshot' => 'Santina',
                'tipo_producto' => 'materia_prima',
                'envase_primario' => 'bins',
                'cantidad_envases_primarios' => 1,
                'kilos_brutos' => 1,
                'kilos_netos_calculados' => 1,
                'kilos_netos_confirmados' => 1,
                'creado_por_user_id' => $this->administrador->id,
                'actualizado_por_user_id' => $this->administrador->id,
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
