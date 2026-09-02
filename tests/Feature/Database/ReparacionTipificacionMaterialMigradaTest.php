<?php

namespace Tests\Feature\Database;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteProveedorMaterial;
use App\Models\ItemMaterial;
use App\Models\MigracionTemporada;
use App\Models\ProveedorMaterial;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Temporadas\ServicioTemporadaGlobal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReparacionTipificacionMaterialMigradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_repara_catalogos_ya_migrados_sin_sobrescribir_tipificaciones_posteriores(): void
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $origen = Temporada::query()->where('activa', true)->firstOrFail();
        $catalogoOrigen = $origen->configuracionMaterial()->firstOrFail();
        $clienteOrigen = $catalogoOrigen->clientes()->with('cliente')->firstOrFail();

        $itemOrigenReparable = $this->crearItem(
            $clienteOrigen->id,
            'ITEM-REPARABLE',
            CategoriaOperacionalMaterial::Insumo,
            $administrador,
        );
        $this->crearItem(
            $clienteOrigen->id,
            'ITEM-MANUAL',
            CategoriaOperacionalMaterial::Insumo,
            $administrador,
        );

        $destino = app(ServicioTemporadaGlobal::class)->guardar([
            'codigo' => 'TEMP-DESTINO-REPARACION',
            'nombre' => 'Temporada destino reparación',
            'activa' => true,
        ], usuarioId: $administrador->id);
        $clienteDestino = $destino->configuracionMaterial()
            ->firstOrFail()
            ->clientes()
            ->where('cliente_id', $clienteOrigen->cliente_id)
            ->firstOrFail();
        $itemDestinoReparable = $this->crearItem(
            $clienteDestino->id,
            $itemOrigenReparable->codigo,
            null,
            $administrador,
        );
        $itemDestinoManual = $this->crearItem(
            $clienteDestino->id,
            'ITEM-MANUAL',
            CategoriaOperacionalMaterial::MaterialPt,
            $administrador,
        );

        $proveedor = ProveedorMaterial::create([
            'codigo' => 'PROV-REPARACION',
            'nombre' => 'Proveedor reparación',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        ClienteProveedorMaterial::create([
            'cliente_id' => $clienteOrigen->cliente_id,
            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'categorias' => ['Embalaje'],
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        MigracionTemporada::create([
            'temporada_origen_id' => $origen->id,
            'temporada_destino_id' => $destino->id,
            'copio_catalogo_validacion' => false,
            'copio_catalogo_materiales' => true,
            'migro_inventario_materiales' => false,
            'activo_destino' => true,
            'resumen' => [
                'materiales' => ['clientes' => 1, 'items' => 2],
            ],
            'creado_por_user_id' => $administrador->id,
        ]);

        $migracion = require database_path(
            'migrations/2026_09_02_150000_reparar_tipificacion_items_materiales_migrados.php',
        );
        $migracion->up();
        $migracion->up();

        $this->assertSame(
            CategoriaOperacionalMaterial::Insumo,
            $itemDestinoReparable->refresh()->categoria_operacional,
        );
        $this->assertSame(
            CategoriaOperacionalMaterial::MaterialPt,
            $itemDestinoManual->refresh()->categoria_operacional,
        );

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/materiales/recepciones/catalogos')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $proveedor->id,
                'codigo' => 'PROV-REPARACION',
            ])
            ->assertJsonFragment([
                'id' => $itemDestinoReparable->id,
                'categoria_operacional' => CategoriaOperacionalMaterial::Insumo->value,
            ]);
    }

    private function crearItem(
        string $clienteMaterialId,
        string $codigo,
        ?CategoriaOperacionalMaterial $categoriaOperacional,
        User $usuario,
    ): ItemMaterial {
        return ItemMaterial::create([
            'cliente_material_id' => $clienteMaterialId,
            'codigo' => $codigo,
            'nombre' => 'Ítem '.$codigo,
            'categoria' => 'Embalaje',
            'categoria_operacional' => $categoriaOperacional,
            'unidad_medida' => 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
        ]);
    }
}
