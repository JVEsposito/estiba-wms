<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\ArticuloValidacion;
use App\Models\CombinacionValidacion;
use App\Models\Dispositivo;
use App\Models\OrigenValidacion;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministracionValidacionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_configura_temporada_articulo_origen_y_combinacion(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);

        $temporadaId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/validacion/temporadas', [
                'codigo' => ' 2026-2027 ',
                'nombre' => ' Temporada cerezas ',
                'activa' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.codigo', '2026-2027')
            ->assertJsonPath('data.activa', true)
            ->json('data.id');

        $articuloId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/validacion/articulos', [
                'temporada_id' => $temporadaId,
                'especie' => 'Cereza',
                'variedad' => 'Santina',
                'calibre' => '2j',
                'envase' => 'Caja 5 kg',
                'codigo_externo' => 'cer-san-2j',
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.calibre', '2J')
            ->json('data.id');

        $origenId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/validacion/origenes', [
                'temporada_id' => $temporadaId,
                'cliente' => 'DIS',
                'marca' => 'Atlas',
                'csg' => '105410',
                'predio' => 'OLM',
                'activo' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $combinacionId = $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/validacion/combinaciones', [
                'temporada_id' => $temporadaId,
                'articulo_validacion_id' => $articuloId,
                'origen_validacion_id' => $origenId,
                'codigo_externo' => 'VAL-001',
                'activo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.articulo.id', $articuloId)
            ->assertJsonPath('data.origen.id', $origenId)
            ->json('data.id');

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/validacion')
            ->assertOk()
            ->assertJsonPath('temporada.id', $temporadaId)
            ->assertJsonPath('combinaciones.0.id', $combinacionId);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/validacion/catalogos')
            ->assertOk()
            ->assertJsonPath('combinaciones.0.id', $combinacionId)
            ->assertJsonPath('temporada.version_catalogo', 4);
    }

    public function test_importador_previsualiza_y_confirma_csv_sin_desactivar_ausencias(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $temporada = Temporada::create([
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
            'version_catalogo' => 1,
        ]);
        $articuloAnterior = ArticuloValidacion::create([
            'temporada_id' => $temporada->id,
            'especie' => 'Cereza',
            'variedad' => 'Lapins',
            'calibre' => 'J',
            'envase' => 'Caja 5 kg',
            'activo' => true,
        ]);

        $archivo = UploadedFile::fake()->createWithContent(
            'temporada.csv',
            "especie;variedad;calibre;envase;cliente;marca;csg;predio;codigo_articulo;codigo_origen;codigo_combinacion\n".
            "Cereza;Santina;2J;Caja 5 kg;DIS;Atlas;105410;OLM;CER-SAN-2J;ORI-01;VAL-01\n".
            "Cereza;Regina;3J;Caja 2,5 kg;DIS;Premium;105411;RIO;CER-REG-3J;ORI-02;VAL-02\n",
        );

        $importacionId = $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/validacion/importaciones/previsualizar', [
                'temporada_id' => $temporada->id,
                'archivo' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.resumen.filas_validas', 2)
            ->assertJsonPath('data.resumen.filas_con_error', 0)
            ->json('data.id');

        $this->assertDatabaseCount('articulos_validacion', 1);

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/validacion/importaciones/{$importacionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.resumen.creados.articulos', 2)
            ->assertJsonPath('data.resumen.creados.combinaciones', 2)
            ->assertJsonPath('data.resumen.version_catalogo_resultante', 2);

        $this->assertDatabaseHas('articulos_validacion', [
            'id' => $articuloAnterior->id,
            'activo' => true,
        ]);
        $this->assertDatabaseHas('origenes_validacion', [
            'cliente' => 'DIS',
            'marca' => 'Atlas',
            'csg' => '105410',
        ]);
        $this->assertDatabaseCount('combinaciones_validacion', 2);
    }

    public function test_importacion_con_error_no_puede_confirmarse(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $temporada = Temporada::create([
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
        ]);
        $archivo = UploadedFile::fake()->createWithContent(
            'incompleta.csv',
            "especie;variedad;calibre;envase;cliente;marca;csg\nCereza;Santina;2J;Caja 5 kg;DIS;Atlas;\n",
        );

        $importacionId = $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/validacion/importaciones/previsualizar', [
                'temporada_id' => $temporada->id,
                'archivo' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'con_errores')
            ->assertJsonPath('data.resumen.filas_con_error', 1)
            ->json('data.id');

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/validacion/importaciones/{$importacionId}/confirmar")
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
    }

    public function test_validador_no_administra_catalogos_y_combinacion_no_habilitada_se_rechaza(): void
    {
        $validador = User::factory()->create(['rol' => RolUsuario::Validador]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'VAL-ADMIN-01',
            'nombre' => 'PDA validación',
            'activo' => true,
        ]);
        $token = $validador->crearTokenParaDispositivo($dispositivo, 'validacion-test')->plainTextToken;
        $temporada = Temporada::create([
            'codigo' => '2026-2027',
            'nombre' => 'Temporada 2026-2027',
            'activa' => true,
        ]);
        $articulo = ArticuloValidacion::create([
            'temporada_id' => $temporada->id,
            'especie' => 'Cereza',
            'variedad' => 'Santina',
            'calibre' => '2J',
            'envase' => 'Caja 5 kg',
            'activo' => true,
        ]);
        $origenHabilitado = OrigenValidacion::create([
            'temporada_id' => $temporada->id,
            'cliente' => 'DIS',
            'marca' => 'Atlas',
            'csg' => '105410',
            'activo' => true,
        ]);
        $origenNoHabilitado = OrigenValidacion::create([
            'temporada_id' => $temporada->id,
            'cliente' => 'OTRO',
            'marca' => 'Otra marca',
            'csg' => '999999',
            'activo' => true,
        ]);
        CombinacionValidacion::create([
            'temporada_id' => $temporada->id,
            'articulo_validacion_id' => $articulo->id,
            'origen_validacion_id' => $origenHabilitado->id,
            'activo' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/administracion/validacion')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/validacion/pallets', [
                'operacion_id' => (string) Str::uuid(),
                'numero_folio' => 'PAL-NO-HABILITADO',
                'tipo_bulto' => 'pallet',
                'cantidad_cajas' => 120,
                'temporada_id' => $temporada->id,
                'catalogo_version' => 1,
                'articulo_validacion_id' => $articulo->id,
                'origen_validacion_id' => $origenNoHabilitado->id,
                'resultado' => 'aprobado',
                'generado_dispositivo_at' => now()->toAtomString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
    }
}
