<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteMaterial;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\RecetaMaterial;
use App\Models\RegularizacionItemMaterial;
use App\Models\User;
use App\Models\VersionRecetaMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegularizacionItemMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolida_pt_duplicado_y_versiona_receta_sobre_mp_canonico(): void
    {
        [$administrador, $token] = $this->crearAdministrador();
        $cliente = ClienteMaterial::query()
            ->with('cliente')
            ->where('codigo', 'GENERAL')
            ->firstOrFail();
        $mp = $this->crearItem($administrador, $cliente, [
            'codigo' => 'MP-4006036',
            'nombre' => 'Esquinero 40 x 60 x 36',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialMp,
        ]);
        $pt = $this->crearItem($administrador, $cliente, [
            'codigo' => 'PT-4006036',
            'nombre' => 'Esquinero 40 x 60 x 36 preparado',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialPt,
        ]);

        $recetaId = $this->withToken($token)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $cliente->cliente_id,
                'item_salida_id' => $pt->id,
                'nombre' => 'Preparación esquinero 4006036',
                'cantidad_base_salida' => 1,
                'unidades_por_folio_salida' => 1,
                'componentes' => [[
                    'item_entrada_id' => $mp->id,
                    'cantidad_estandar' => 1,
                    'es_componente_principal' => true,
                    'factor_conversion' => 1,
                    'merma_estandar_porcentaje' => 0,
                    'tolerancia_porcentaje' => 0,
                ]],
            ])
            ->assertCreated()
            ->json('data.id');
        $operacionId = (string) Str::uuid();

        $response = $this->withToken($token)
            ->postJson("/api/administracion/materiales/items/{$pt->id}/regularizar", [
                'operacion_id' => $operacionId,
                'item_canonico_id' => $mp->id,
                'motivo' => 'Ambos códigos representan físicamente el mismo material.',
            ])
            ->assertOk()
            ->assertJsonPath('data.item_duplicado.id', $pt->id)
            ->assertJsonPath('data.item_canonico.id', $mp->id)
            ->assertJsonCount(1, 'data.recetas_versionadas');

        $regularizacionId = $response->json('data.id');
        $this->assertFalse($pt->refresh()->activo);
        $this->assertSame($mp->id, RecetaMaterial::findOrFail($recetaId)->item_salida_id);
        $this->assertDatabaseHas('versiones_recetas_materiales', [
            'receta_material_id' => $recetaId,
            'numero_version' => 1,
            'estado' => 'retirada',
        ]);
        $this->assertDatabaseHas('versiones_recetas_materiales', [
            'receta_material_id' => $recetaId,
            'numero_version' => 2,
            'estado' => 'activa',
        ]);
        $this->assertSame(2, VersionRecetaMaterial::query()
            ->where('receta_material_id', $recetaId)->count());
        $this->assertDatabaseHas('regularizaciones_items_materiales', [
            'id' => $regularizacionId,
            'operacion_id' => $operacionId,
            'item_duplicado_id' => $pt->id,
            'item_canonico_id' => $mp->id,
            'user_id' => $administrador->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/administracion/materiales/items/{$pt->id}/regularizar", [
                'operacion_id' => $operacionId,
                'item_canonico_id' => $mp->id,
                'motivo' => 'Ambos códigos representan físicamente el mismo material.',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $regularizacionId);

        $this->assertSame(1, RegularizacionItemMaterial::query()->count());
        $this->assertSame(2, VersionRecetaMaterial::query()
            ->where('receta_material_id', $recetaId)->count());
    }

    public function test_rechaza_consolidar_items_de_distinta_unidad(): void
    {
        [$administrador, $token] = $this->crearAdministrador();
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $mp = $this->crearItem($administrador, $cliente, [
            'codigo' => 'MP-CAJA',
            'unidad_medida' => 'cajas',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialMp,
        ]);
        $pt = $this->crearItem($administrador, $cliente, [
            'codigo' => 'PT-CAJA',
            'unidad_medida' => 'unidades',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialPt,
        ]);

        $this->withToken($token)
            ->postJson("/api/administracion/materiales/items/{$pt->id}/regularizar", [
                'operacion_id' => (string) Str::uuid(),
                'item_canonico_id' => $mp->id,
                'motivo' => 'Intento de regularización con unidades incompatibles.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertTrue($pt->refresh()->activo);
        $this->assertDatabaseCount('regularizaciones_items_materiales', 0);
    }

    public function test_rechaza_consolidar_un_pt_que_aun_tiene_stock(): void
    {
        [$administrador, $token] = $this->crearAdministrador();
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();
        $mp = $this->crearItem($administrador, $cliente, [
            'codigo' => 'MP-CON-STOCK',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialMp,
        ]);
        $pt = $this->crearItem($administrador, $cliente, [
            'codigo' => 'PT-CON-STOCK',
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialPt,
        ]);
        $folio = Folio::create([
            'numero_folio' => 'MAT-PT-CON-STOCK',
            'tipo_bulto' => 'material',
            'estado_operacional' => 'disponible',
            'fecha_ingreso' => now(),
            'activo' => true,
            'origen_sistema' => 'manual',
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $pt->id,
            'categoria_operacional' => CategoriaOperacionalMaterial::MaterialPt,
            'cantidad_inicial' => 5,
            'cantidad_actual' => 5,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'unidades',
        ]);

        $this->withToken($token)
            ->postJson("/api/administracion/materiales/items/{$pt->id}/regularizar", [
                'operacion_id' => (string) Str::uuid(),
                'item_canonico_id' => $mp->id,
                'motivo' => 'Intento de regularización mientras aún existe stock.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio')
            ->assertJsonPath(
                'message',
                'El Material PT duplicado aún posee stock o reservas. Consúmelo o corrígelo antes de consolidar.',
            );

        $this->assertTrue($pt->refresh()->activo);
        $this->assertDatabaseCount('regularizaciones_items_materiales', 0);
    }

    /** @return array{User, string} */
    private function crearAdministrador(): array
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);

        return [$usuario, $usuario->createToken('oficina-regularizacion', ['oficina'])->plainTextToken];
    }

    /** @param array<string, mixed> $datos */
    private function crearItem(User $usuario, ClienteMaterial $cliente, array $datos): ItemMaterial
    {
        return ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'] ?? $datos['codigo'],
            'categoria' => 'Embalaje',
            'categoria_operacional' => $datos['categoria_operacional'],
            'unidad_medida' => $datos['unidad_medida'] ?? 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
