<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteMaterial;
use App\Models\Dispositivo;
use App\Models\ItemMaterial;
use App\Models\ProveedorMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogoRecepcionMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrega_catalogo_operacional_filtrado_para_la_tablet(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $catalogoCliente = ClienteMaterial::query()
            ->with(['cliente', 'temporada.temporadaGlobal'])
            ->where('codigo', 'GENERAL')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->firstOrFail();
        $cliente = $catalogoCliente->cliente;
        $cliente->update(['codigo_folio_materiales' => 'GE']);
        $item = ItemMaterial::create([
            'cliente_material_id' => $catalogoCliente->id,
            'codigo' => 'FILM-TABLET',
            'nombre' => 'Film para prueba de tablet',
            'categoria' => 'Embalaje',
            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $proveedor = ProveedorMaterial::create([
            'codigo' => 'PROV-TABLET',
            'nombre' => 'Proveedor para tablet',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        DB::table('clientes_proveedores_materiales')->insert([
            'id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $operador = User::factory()->create([
            'rol' => RolUsuario::CamareroMateriales,
            'activo' => true,
        ]);
        $dispositivo = Dispositivo::create([
            'codigo' => 'TABLET-CATALOGO',
            'nombre' => 'Tablet catálogo recepción',
            'activo' => true,
        ]);
        $token = $operador
            ->crearTokenParaDispositivo($dispositivo, 'tablet-catalogo')
            ->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/materiales/recepciones/catalogos')
            ->assertOk()
            ->assertJsonPath('temporada.id', $catalogoCliente->temporada->temporada_id)
            ->assertJsonFragment([
                'id' => $cliente->id,
                'cliente_material_id' => $catalogoCliente->id,
                'codigo_folio_materiales' => 'GE',
            ])
            ->assertJsonFragment([
                'id' => $proveedor->id,
                'codigo' => 'PROV-TABLET',
                'nombre' => 'Proveedor para tablet',
            ])
            ->assertJsonFragment([
                'id' => $item->id,
                'cliente_id' => $cliente->id,
                'categoria_operacional' => 'insumo',
                'unidad_medida' => 'rollos',
            ]);

        $this->assertContains($cliente->id, collect($response->json('proveedores'))
            ->firstWhere('id', $proveedor->id)['cliente_ids']);
    }
}
