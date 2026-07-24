<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteMaterial;
use App\Models\ItemMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransformacionMaterialVersionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_nueva_version_y_retira_la_anterior_sin_mutar_historia(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $token = $administrador->createToken('oficina-version-receta', ['oficina'])->plainTextToken;
        $catalogo = ClienteMaterial::query()
            ->with(['cliente', 'temporada'])
            ->where('codigo', 'GENERAL')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->firstOrFail();
        $entrada = $this->crearItem(
            $catalogo,
            $administrador,
            'MP-VERSION',
            'Material sin preparar',
            CategoriaOperacionalMaterial::MaterialMp,
        );
        $salida = $this->crearItem(
            $catalogo,
            $administrador,
            'PT-VERSION',
            'Material preparado',
            CategoriaOperacionalMaterial::MaterialPt,
        );
        $receta = $this->withToken($token)
            ->postJson('/api/materiales/transformaciones/recetas', [
                'cliente_id' => $catalogo->cliente_id,
                'item_salida_id' => $salida->id,
                'nombre' => 'Receta versionada',
                'cantidad_base_salida' => 100,
                'componentes' => [[
                    'item_entrada_id' => $entrada->id,
                    'cantidad_estandar' => 100,
                    'es_componente_principal' => true,
                    'factor_conversion' => 1,
                    'merma_estandar_porcentaje' => 1,
                ]],
            ])
            ->assertCreated()
            ->json('data');
        $versionUnoId = $receta['versiones'][0]['id'];
        $snapshotUno = $receta['versiones'][0]['componentes'][0];

        $actualizada = $this->withToken($token)
            ->postJson("/api/materiales/transformaciones/recetas/{$receta['id']}/versiones", [
                'cantidad_base_salida' => 200,
                'componentes' => [[
                    'item_entrada_id' => $entrada->id,
                    'cantidad_estandar' => 202,
                    'es_componente_principal' => true,
                    'factor_conversion' => 1,
                    'merma_estandar_porcentaje' => 1.5,
                    'tolerancia_porcentaje' => 0.5,
                ]],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.versiones')
            ->assertJsonPath('data.versiones.0.numero_version', 2)
            ->assertJsonPath('data.versiones.0.estado', 'activa')
            ->assertJsonPath('data.versiones.0.cantidad_base_salida', '200.000')
            ->assertJsonPath('data.versiones.1.id', $versionUnoId)
            ->assertJsonPath('data.versiones.1.estado', 'retirada')
            ->json('data');

        $this->assertSame($snapshotUno, $actualizada['versiones'][1]['componentes'][0]);
        $this->assertDatabaseHas('versiones_recetas_materiales', [
            'id' => $versionUnoId,
            'numero_version' => 1,
            'estado' => 'retirada',
            'cantidad_base_salida' => 100,
        ]);
        $this->assertDatabaseHas('versiones_recetas_materiales', [
            'receta_material_id' => $receta['id'],
            'numero_version' => 2,
            'estado' => 'activa',
            'cantidad_base_salida' => 200,
        ]);
    }

    private function crearItem(
        ClienteMaterial $catalogo,
        User $usuario,
        string $codigo,
        string $nombre,
        CategoriaOperacionalMaterial $categoria,
    ): ItemMaterial {
        return ItemMaterial::create([
            'cliente_material_id' => $catalogo->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'categoria' => 'Embalaje',
            'categoria_operacional' => $categoria,
            'unidad_medida' => 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
