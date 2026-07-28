<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\CsgValidacion;
use App\Models\EspecieValidacion;
use App\Models\ProductorCsg;
use App\Models\Temporada;
use App\Models\User;
use App\Models\VariedadValidacion;
use App\Services\Consultas\ServicioConsultaSag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultaOperacionalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_sag_guarda_actualiza_y_vincula_el_productor_sin_habilitarlo(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-CONSULTAS',
            'nombre' => 'Temporada consultas',
            'activa' => true,
        ]);
        $catalogo = CsgValidacion::create([
            'temporada_id' => $temporada->id,
            'codigo' => '105410',
            'predio' => 'Dato anterior',
            'activo' => false,
        ]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response($this->respuestaSag(), 200),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '105410',
            ])
            ->assertOk()
            ->assertJsonPath('cantidad', 1)
            ->assertJsonPath('data.0.codigo', '105410')
            ->assertJsonPath('data.0.estado_sag', 'activo')
            ->assertJsonPath('data.0.estado_asociacion', 'pendiente_cliente')
            ->assertJsonPath('data.0.predio', 'Los Cerezos');

        $productor = ProductorCsg::query()->firstOrFail();
        $this->assertNull($productor->rut);
        $this->assertSame([
            'CEREZA - BING',
            'CEREZA - LAPINS',
            'CEREZA - RAINIER',
        ], $productor->especies);
        $this->assertSame($productor->id, $catalogo->fresh()->productor_csg_id);
        $this->assertFalse($catalogo->fresh()->activo);
        $this->assertDatabaseHas('especies_validacion', [
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza',
            'activo' => true,
        ]);
        $this->assertSame(
            ['Bing', 'Lapins', 'Rainier'],
            VariedadValidacion::query()
                ->whereHas('especie', fn ($query) => $query
                    ->where('temporada_id', $temporada->id)
                    ->where('nombre', 'Cereza'))
                ->orderBy('nombre')
                ->pluck('nombre')
                ->all(),
        );
        $this->assertSame(3, $catalogo->fresh()->variedades()->count());
        $this->assertDatabaseCount('productores_csg', 1);
        $this->assertDatabaseHas('consultas_sag', [
            'tipo_busqueda' => 'codigo_sag',
            'valor_normalizado' => '105410',
            'estado' => 'exitosa',
            'cantidad_resultados' => 1,
        ]);

        $this->postJson('/api/consultas/sag', [
            'tipo' => 'codigo_sag',
            'valor' => ' 105410 ',
        ])->assertOk();
        $this->assertDatabaseCount('productores_csg', 1);
        $this->assertDatabaseCount('consultas_sag', 2);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === ServicioConsultaSag::URL
            && $request['searchRutCodigo'] === 'Codigo SAG'
            && $request['tipo_part'] === '2'
            && $request['cod_sag'] === '105410');
    }

    public function test_consulta_sag_repara_codificacion_mixta_separa_listas_y_sincroniza_sin_duplicar(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        Temporada::query()->update(['activa' => false]);
        $temporada = Temporada::create([
            'codigo' => 'TEMP-CEREZAS',
            'nombre' => 'Temporada cerezas',
            'activa' => true,
        ]);
        $especieExistente = EspecieValidacion::create([
            'temporada_id' => $temporada->id,
            'nombre' => 'Cereza',
            'activo' => false,
        ]);
        $lapinsExistente = VariedadValidacion::create([
            'especie_validacion_id' => $especieExistente->id,
            'nombre' => 'Lapins',
            'activo' => false,
        ]);
        $catalogo = CsgValidacion::create([
            'temporada_id' => $temporada->id,
            'codigo' => '3129422',
            'predio' => 'Pendiente de verificación',
            'activo' => false,
        ]);
        ProductorCsg::create([
            'codigo' => '3129422',
            'razon_social' => 'SIRZO BALTAZAR CARO LIZANA',
            'predio' => 'PARCELA N 1 STA GABRIELA DE LOS LINGUES',
            'direccion' => 'DirecciÃ³n histÃ³rica',
            'estado_sag' => 'activo',
            'tipo_codigo' => 'CSG',
            'especies' => [],
            'fuente_url' => ServicioConsultaSag::URL,
            'primera_verificacion_at' => now()->subDay(),
            'ultima_verificacion_at' => now()->subDay(),
            'ultima_consulta_user_id' => $administrador->id,
            'respuesta_hash' => hash('sha256', 'respuesta-anterior'),
            'datos_fuente' => [],
        ]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response($this->respuestaSagCodificacionMixtaConLista(), 200),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '3129422',
            ])
            ->assertOk()
            ->assertJsonPath('cantidad', 1)
            ->assertJsonPath('data.0.razon_social', 'SIRZO BALTAZAR CARO LIZANA')
            ->assertJsonPath(
                'data.0.direccion',
                'Dirección:PARCELA N 1 STA GABRIELA DE LOS LINGUES, SAN FERNANDO, DEL LIBERTADOR GRAL. BERNARDO O HIGGINS',
            )
            ->assertJsonPath('data.0.especies.0', 'CEREZA - LAPINS')
            ->assertJsonPath('data.0.especies.1', 'CEREZA - SANTINA')
            ->assertJsonPath('data.0.especies_variedades.0.especie', 'CEREZA')
            ->assertJsonPath('data.0.especies_variedades.0.variedad', 'LAPINS')
            ->assertJsonPath('data.0.especies_variedades.1.variedad', 'SANTINA');

        $especie = EspecieValidacion::query()
            ->where('temporada_id', $temporada->id)
            ->sole();
        $this->assertSame($temporada->id, $especie->temporada_id);
        $this->assertSame('Cereza', $especie->nombre);
        $this->assertFalse($especie->activo);
        $this->assertSame(
            ['Lapins', 'Santina'],
            $especie->variedades()->orderBy('nombre')->pluck('nombre')->all(),
        );
        $this->assertFalse($lapinsExistente->fresh()->activo);
        $this->assertFalse($catalogo->fresh()->activo);
        $this->assertSame(2, $catalogo->fresh()->variedades()->count());
        $this->assertDatabaseMissing('productores_csg', [
            'codigo' => '3129422',
            'direccion' => 'DirecciÃ³n histÃ³rica',
        ]);
        $this->assertDatabaseHas('productores_csg', [
            'codigo' => '3129422',
            'direccion' => 'Dirección:PARCELA N 1 STA GABRIELA DE LOS LINGUES, SAN FERNANDO, DEL LIBERTADOR GRAL. BERNARDO O HIGGINS',
        ]);

        $this->postJson('/api/consultas/sag', [
            'tipo' => 'codigo_sag',
            'valor' => '3129422',
        ])->assertOk();

        $this->assertDatabaseCount('especies_validacion', 1);
        $this->assertDatabaseCount('variedades_validacion', 2);
        $this->assertDatabaseCount('csg_variedades_validacion', 2);
        $this->assertDatabaseCount('productores_csg', 1);
    }

    public function test_respeta_permisos_de_consulta_y_asociacion_a_cliente(): void
    {
        $digitador = User::factory()->create(['rol' => RolUsuario::DigitadorMateriaPrima]);
        $supervisor = User::factory()->create(['rol' => RolUsuario::SupervisorFrio]);
        $consulta = User::factory()->create(['rol' => RolUsuario::Consulta]);
        $cliente = Cliente::create([
            'codigo' => 'EXP-01',
            'nombre' => 'Exportadora Uno',
            'activo' => true,
        ]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response($this->respuestaSag(), 200),
        ]);

        $productorId = $this->actingAs($digitador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '105410',
            ])
            ->assertOk()
            ->json('data.0.id');

        $this->postJson("/api/consultas/productores/{$productorId}/clientes", [
            'cliente_id' => $cliente->id,
        ])->assertForbidden();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/consultas/productores/{$productorId}/clientes", [
                'cliente_id' => $cliente->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado_asociacion', 'asociado')
            ->assertJsonPath('data.clientes.0.id', $cliente->id);

        $this->getJson('/api/consultas/buscar?q=105410&tipo=todos')
            ->assertOk()
            ->assertJsonPath('productores.0.codigo', '105410')
            ->assertJsonPath('productores.0.clientes.0', 'Exportadora Uno');

        $this->actingAs($consulta, 'sanctum')
            ->getJson('/api/consultas/resumen')
            ->assertForbidden();
        $this->postJson('/api/consultas/sag', [
            'tipo' => 'codigo_sag',
            'valor' => '105410',
        ])->assertForbidden();
    }

    public function test_consulta_sag_acepta_la_respuesta_real_de_tres_columnas(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response($this->respuestaSagTresColumnas(), 200),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '123225',
            ])
            ->assertOk()
            ->assertJsonPath('cantidad', 1)
            ->assertJsonPath('data.0.codigo', '123225')
            ->assertJsonPath('data.0.estado_sag', 'activo')
            ->assertJsonPath('data.0.tipo_codigo', 'CSG')
            ->assertJsonPath('data.0.razon_social', 'Agricola Las Vegas SPA')
            ->assertJsonPath('data.0.predio', 'Fundo Santa Elena de Los Niches')
            ->assertJsonPath(
                'data.0.direccion',
                'Dirección:Fundo santa Elena de los Niches Lote B, CURICO, DEL MAULE',
            )
            ->assertJsonPath('data.0.especies', []);

        $this->assertDatabaseHas('productores_csg', [
            'codigo' => '123225',
            'razon_social' => 'Agricola Las Vegas SPA',
            'estado_sag' => 'activo',
            'tipo_codigo' => 'CSG',
        ]);
        $this->assertDatabaseHas('consultas_sag', [
            'tipo_busqueda' => 'codigo_sag',
            'valor_normalizado' => '123225',
            'estado' => 'exitosa',
            'cantidad_resultados' => 1,
        ]);
    }

    public function test_consulta_sag_ignora_la_ayuda_emergente_entre_estado_y_tipo_codigo(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response($this->respuestaSagConAyudaEmergente(), 200),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '123225',
            ])
            ->assertOk()
            ->assertJsonPath('cantidad', 1)
            ->assertJsonPath('data.0.codigo', '123225')
            ->assertJsonPath('data.0.estado_sag', 'activo')
            ->assertJsonPath('data.0.tipo_codigo', 'CSG')
            ->assertJsonPath('data.0.razon_social', 'Agricola Las Vegas SPA')
            ->assertJsonPath('data.0.predio', 'Fundo Santa Elena de Los Niches')
            ->assertJsonPath('data.0.especies.0', 'KIWI - HAYWARD');

        $this->assertDatabaseHas('consultas_sag', [
            'valor_normalizado' => '123225',
            'estado' => 'exitosa',
            'cantidad_resultados' => 1,
        ]);
    }

    public function test_falla_del_servicio_sag_no_modifica_productores_y_queda_auditada(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        Http::fake([
            ServicioConsultaSag::URL => Http::response('Servicio temporalmente fuera de línea', 503),
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/consultas/sag', [
                'tipo' => 'codigo_sag',
                'valor' => '105410',
            ])
            ->assertStatus(503)
            ->assertJsonPath('codigo', 'servicio_sag_no_disponible');

        $this->assertDatabaseCount('productores_csg', 0);
        $this->assertDatabaseHas('consultas_sag', [
            'estado' => 'error',
            'cantidad_resultados' => 0,
        ]);
    }

    private function respuestaSag(): string
    {
        $html = <<<'HTML'
        <!doctype html>
        <html><body>
        <table>
            <thead><tr><th>Código SAG</th><th>Predio / Establecimiento</th><th>Razón Social</th><th>Especies</th></tr></thead>
            <tbody><tr>
                <td>105410 (ACTIVO) (CSG)</td>
                <td><strong>Los Cerezos</strong><br>LOTE B, PARCELA 25 STA LUISA, REQUINOA</td>
                <td>SOCIEDAD AGRÍCOLA LOS CEREZOS SPA</td>
                <td>CEREZA - BING<br>CEREZA - LAPINS<br>CEREZA - RAINIER</td>
            </tr></tbody>
        </table>
        </body></html>
        HTML;

        return mb_convert_encoding($html, 'Windows-1252', 'UTF-8');
    }

    private function respuestaSagTresColumnas(): string
    {
        $html = <<<'HTML'
        <!doctype html>
        <html><body>
        <table>
            <thead><tr><th>Código SAG</th><th>Predio / Establecimiento</th><th>Razón Social</th></tr></thead>
            <tbody><tr>
                <td>123225 (ACTIVO) (CSG)</td>
                <td><strong>Fundo Santa Elena de Los Niches</strong><br>Dirección:Fundo santa Elena de los Niches Lote B, CURICO, DEL MAULE</td>
                <td>Agricola Las Vegas SPA</td>
            </tr></tbody>
        </table>
        </body></html>
        HTML;

        return mb_convert_encoding($html, 'Windows-1252', 'UTF-8');
    }

    private function respuestaSagCodificacionMixtaConLista(): string
    {
        $html = <<<'HTML'
        <!doctype html>
        <html><body>
        <!-- MARCADOR_ISO -->
        <table>
            <thead><tr><th>Código SAG</th><th>Predio / Establecimiento</th><th>Razón Social</th><th>Especies</th></tr></thead>
            <tbody><tr>
                <td>3129422 (ACTIVO) (CSG)</td>
                <td>
                    <strong>SIRZO BALTAZAR CARO LIZANA</strong><br>
                    Dirección:PARCELA N 1 STA GABRIELA DE LOS LINGUES, SAN FERNANDO, DEL LIBERTADOR GRAL. BERNARDO O HIGGINS
                </td>
                <td>SIRZO BALTAZAR CARO LIZANA</td>
                <td>
                    <ul>
                        <li>CEREZA - LAPINS</li>
                        <li>CEREZA - SANTINA</li>
                    </ul>
                </td>
            </tr></tbody>
        </table>
        </body></html>
        HTML;

        return str_replace(
            'MARCADOR_ISO',
            mb_convert_encoding('ñ', 'Windows-1252', 'UTF-8'),
            $html,
        );
    }

    private function respuestaSagConAyudaEmergente(): string
    {
        $html = <<<'HTML'
        <!doctype html>
        <html><body>
        <table>
            <thead><tr><th>Código SAG</th><th>Predio / Establecimiento</th><th>Razón Social</th><th>Especies</th></tr></thead>
            <tbody><tr>
                <td>
                    123225<br>(ACTIVO)
                    <div id="photo_container">
                        <a href="#">
                            <span>
                                <p><b>INACTIVO:</b> Recuerde activar su Código.</p>
                                <p><b>BLOQUEADO:</b> Envíe documentación para desbloquearlo.</p>
                            </span>
                        </a>
                    </div>
                    <br>(CSG)
                </td>
                <td><strong>Fundo Santa Elena de Los Niches</strong><br>Dirección:Fundo santa Elena de los Niches Lote B, CURICO, DEL MAULE</td>
                <td><strong>Agricola Las Vegas SPA</strong></td>
                <td><li>KIWI - HAYWARD</li></td>
            </tr></tbody>
        </table>
        </body></html>
        HTML;

        return mb_convert_encoding($html, 'Windows-1252', 'UTF-8');
    }
}
