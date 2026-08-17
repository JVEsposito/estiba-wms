<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\CalibreValidacion;
use App\Models\Cliente;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Temporada;
use App\Models\User;
use App\Models\VariedadValidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReinicioOperacionalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_un_administrador_activo_puede_previsualizar_y_ejecutar_el_reinicio(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/administracion/temporadas/{$temporada->id}/reinicio-operacional")
            ->assertForbidden();

        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'password' => 'password',
        ]);
        $folio = $this->crearFolio($temporada, 'PT-PROTEGIDO', 'pallet');

        $this->actingAs($administrador, 'sanctum')
            ->postJson(
                "/api/administracion/temporadas/{$temporada->id}/reinicio-operacional",
                $this->confirmacion($temporada, password: 'incorrecta'),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('folios', ['id' => $folio->id]);
        $this->assertDatabaseCount('reinicios_operacionales', 0);
    }

    public function test_reinicia_pt_y_mp_pero_conserva_temporada_catalogos_y_bodega(): void
    {
        $temporada = Temporada::query()->where('activa', true)->firstOrFail();
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'password' => 'password',
        ]);
        $folioPt = $this->crearFolio($temporada, 'PT-RESET-001', 'pallet');
        DB::table('secuencias_validacion_folio')->insert([
            'numero_folio' => $folioPt->numero_folio,
            'ultimo_intento' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [$folioBodega, $itemBodega] = $this->crearExistenciaBodega($temporada, $administrador);
        $materiaPrima = $this->crearOperacionMateriaPrima($temporada);

        $vistaPrevia = $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/administracion/temporadas/{$temporada->id}/reinicio-operacional")
            ->assertOk()
            ->assertJsonPath('data.temporada.id', $temporada->id)
            ->assertJsonPath('data.frase_confirmacion', "REINICIAR {$temporada->codigo}")
            ->assertJsonPath('data.resumen.frigorifico.folios', 1)
            ->assertJsonPath('data.resumen.materia_prima.recepciones_romana', 1)
            ->assertJsonPath('data.resumen.materia_prima.lotes', 1);
        $this->assertContains(
            'todos los catálogos y datos operacionales de Bodega',
            $vistaPrevia->json('data.se_conserva'),
        );

        $operacionId = (string) Str::uuid();
        $payload = $this->confirmacion($temporada, $operacionId);
        $this->postJson(
            "/api/administracion/temporadas/{$temporada->id}/reinicio-operacional",
            $payload,
        )
            ->assertCreated()
            ->assertJsonPath('reutilizado', false)
            ->assertJsonPath('data.resumen_despues.frigorifico.folios', 0)
            ->assertJsonPath('data.resumen_despues.materia_prima.recepciones_romana', 0);

        $this->assertDatabaseMissing('folios', ['id' => $folioPt->id]);
        $this->assertDatabaseMissing('recepciones_romana', ['id' => $materiaPrima['recepcion_id']]);
        $this->assertDatabaseMissing('lotes_materia_prima', ['id' => $materiaPrima['lote_id']]);
        $this->assertDatabaseCount('validaciones_mp', 0);
        $this->assertDatabaseCount('segmentos_validacion_mp', 0);
        $this->assertDatabaseCount('procesos_hidrocooler_materia_prima', 0);
        $this->assertDatabaseCount('movimientos_envases', 0);

        $this->assertDatabaseHas('temporadas', [
            'id' => $temporada->id,
            'activa' => true,
        ]);
        $this->assertDatabaseHas('especies_validacion', ['id' => $materiaPrima['especie_id']]);
        $this->assertDatabaseHas('items_materiales', ['id' => $itemBodega->id]);
        $this->assertDatabaseHas('folios', [
            'id' => $folioBodega->id,
            'tipo_bulto' => 'material',
        ]);
        $this->assertDatabaseHas('folios_materiales', ['folio_id' => $folioBodega->id]);
        $this->assertDatabaseHas('secuencias_validacion_folio', [
            'numero_folio' => $folioPt->numero_folio,
            'ultimo_intento' => 2,
        ]);
        $this->assertDatabaseCount('correlativos_recepcion_romana', 1);
        $this->assertDatabaseCount('reinicios_operacionales', 1);

        $this->postJson(
            "/api/administracion/temporadas/{$temporada->id}/reinicio-operacional",
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('reutilizado', true)
            ->assertJsonPath('data.operacion_id', $operacionId);
        $this->assertDatabaseCount('reinicios_operacionales', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmacion(
        Temporada $temporada,
        ?string $operacionId = null,
        string $password = 'password',
    ): array {
        return [
            'operacion_id' => $operacionId ?? (string) Str::uuid(),
            'motivo' => 'Cierre controlado de la etapa de pruebas integrales.',
            'password' => $password,
            'confirmacion' => "REINICIAR {$temporada->codigo}",
            'confirmar_exclusion_bodega' => true,
            'confirmar_preservar_configuracion' => true,
        ];
    }

    private function crearFolio(
        Temporada $temporada,
        string $numero,
        string $tipo,
    ): Folio {
        return Folio::query()->create([
            'temporada_id' => $temporada->id,
            'numero_folio' => $numero,
            'tipo_bulto' => $tipo,
            'estado_operacional' => 'disponible',
            'fecha_ingreso' => now(),
            'activo' => true,
        ]);
    }

    /** @return array{Folio, ItemMaterial} */
    private function crearExistenciaBodega(
        Temporada $temporada,
        User $administrador,
    ): array {
        $clienteMaterialId = DB::table('clientes_materiales as cm')
            ->join('temporadas_materiales as tm', 'tm.id', '=', 'cm.temporada_material_id')
            ->where('tm.temporada_id', $temporada->id)
            ->value('cm.id');
        $item = ItemMaterial::query()->create([
            'cliente_material_id' => $clienteMaterialId,
            'codigo' => 'ITEM-BODEGA-PRESERVADO',
            'nombre' => 'Existencia que no debe borrarse',
            'categoria' => 'Prueba',
            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
            'unidad_medida' => 'unidad',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $folio = $this->crearFolio($temporada, 'MAT-PRESERVADO-001', 'material');
        FolioMaterial::query()->create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
            'cantidad_inicial' => 25,
            'cantidad_actual' => 25,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'unidad',
        ]);

        return [$folio, $item];
    }

    /** @return array{recepcion_id: string, lote_id: string, especie_id: string} */
    private function crearOperacionMateriaPrima(Temporada $temporada): array
    {
        $cliente = Cliente::query()->create([
            'codigo' => 'CLIENTE-RESET-MP',
            'nombre' => 'Cliente reset materia prima',
            'activo' => true,
        ]);
        $operador = User::factory()->create(['rol' => RolUsuario::OperadorRomana]);
        $validador = User::factory()->create(['rol' => RolUsuario::ValidadorMp]);
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);

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
                'numero_guia_despacho' => 'GUIA-RESET-MP',
                'patente_camion' => 'ABCD12',
                'rut_conductor' => '12.345.678-5',
                'nombre_conductor' => 'Transportista de prueba',
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
        ])->assertOk();

        $especie = EspecieValidacion::query()->create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza reset',
            'activo' => true,
        ]);
        $variedad = VariedadValidacion::query()->create([
            'especie_validacion_id' => $especie->id,
            'nombre' => 'Santina reset',
            'activo' => true,
        ]);
        $calibre = CalibreValidacion::query()->create([
            'especie_validacion_id' => $especie->id,
            'nombre' => '28 mm reset',
            'activo' => true,
        ]);
        $csg = CsgValidacion::query()->create([
            'temporada_id' => $temporada->id,
            'codigo' => 'CSG-RESET-01',
            'predio' => 'Predio de prueba',
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
            ->json('data.segmentos.0.id');

        $lote = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/materia-prima/lotes', [
                'operacion_id' => (string) Str::uuid(),
                'segmento_validacion_mp_id' => $segmentoId,
                'numero_lote' => 'LOTE-RESET-001',
                'csg_validacion_id' => $csg->id,
                'sdp' => '987654321',
                'ggn' => '1234567890123',
                'fecha_cosecha' => now()->subDay()->toDateString(),
                'predio' => 'Predio de prueba',
                'especie_validacion_id' => $especie->id,
                'variedad_validacion_id' => $variedad->id,
                'calibre_validacion_id' => $calibre->id,
                'cuartel' => 'C-12',
                'tipo_producto' => 'materia_prima',
                'envase_primario' => 'bins',
                'envase_secundario' => 'totes',
                'cantidad_envases_primarios' => 48,
                'cantidad_envases_secundarios' => 10,
                'kilos_brutos' => 19000,
                'kilos_netos_confirmados' => 18000,
                'requiere_hidrocooler' => true,
            ])
            ->assertCreated()
            ->json('data');
        $lote = $this->postJson("/api/materia-prima/lotes/{$lote['id']}/confirmar", [
            'operacion_id' => (string) Str::uuid(),
            'version_conocida' => $lote['version'],
        ])
            ->assertOk()
            ->json('data');
        $this->postJson("/api/materia-prima/lotes/{$lote['id']}/hidrocooler/iniciar", [
            'operacion_id' => (string) Str::uuid(),
            'equipo' => 'HIDRO-RESET',
            'inicio_at' => now()->subMinutes(15)->toAtomString(),
            'temperatura_inicial_c' => 18,
            'temperatura_objetivo_c' => 4,
        ])->assertOk();

        return [
            'recepcion_id' => $recepcion['id'],
            'lote_id' => $lote['id'],
            'especie_id' => $especie->id,
        ];
    }
}
