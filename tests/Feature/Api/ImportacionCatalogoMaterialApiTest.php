<?php

namespace Tests\Feature\Api;

use App\Enums\RolUsuario;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ImportacionCatalogoMaterial;
use App\Models\ItemMaterial;
use App\Models\User;
use App\Services\Materiales\LectorPlanillaMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ImportacionCatalogoMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_previsualiza_y_confirma_catalogo_sin_tocar_inventario(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $existente = $this->crearItem($administrador, [
            'codigo' => 'FILM-01',
            'nombre' => 'Film anterior',
            'codigo_externo' => 'ERP-FILM',
        ]);
        $ausente = $this->crearItem($administrador, [
            'codigo' => 'ZUNCHO-01',
            'nombre' => 'Zuncho plástico',
            'activo' => false,
        ]);
        $archivo = UploadedFile::fake()->createWithContent(
            'formatos.csv',
            "codigo;nombre;categoria;unidad_medida;codigo_externo;activo\n".
            "FILM-01;Film stretch reforzado;Embalaje;ROLLOS;;si\n".
            "CAJ-5KG;Caja cartón 5 kg;Cajas;UNIDAD;ERP-CAJ-5;si\n",
        );

        $importacionId = $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonPath('data.resumen.filas_validas', 2)
            ->assertJsonPath('data.resumen.nuevos_estimados', 1)
            ->assertJsonPath('data.resumen.actualizaciones_estimadas', 1)
            ->assertJsonMissingPath('data.filas.0.huella_catalogo')
            ->json('data.id');

        $this->assertDatabaseCount('items_materiales', 2);
        $this->assertDatabaseCount('folios_materiales', 0);

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/materiales/importaciones/{$importacionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.resumen.creados', 1)
            ->assertJsonPath('data.resumen.actualizados', 1)
            ->assertJsonPath('data.resumen.sin_cambios', 0)
            ->assertJsonPath('data.confirmado_por.id', $administrador->id);

        $this->assertDatabaseHas('items_materiales', [
            'id' => $existente->id,
            'nombre' => 'Film stretch reforzado',
            'codigo_externo' => 'ERP-FILM',
            'unidad_medida' => 'rollos',
            'activo' => true,
            'origen_sistema' => 'importacion_catalogo',
        ]);
        $this->assertDatabaseHas('items_materiales', [
            'codigo' => 'CAJ-5KG',
            'nombre' => 'Caja cartón 5 kg',
            'unidad_medida' => 'unidad',
            'codigo_externo' => 'ERP-CAJ-5',
        ]);
        $this->assertDatabaseHas('items_materiales', [
            'id' => $ausente->id,
            'activo' => false,
            'origen_sistema' => 'manual',
        ]);
        $this->assertDatabaseCount('items_materiales', 3);
        $this->assertDatabaseCount('folios', 0);
        $this->assertDatabaseCount('folios_materiales', 0);

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/materiales/importaciones/{$importacionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.resumen.creados', 1)
            ->assertJsonPath('data.resumen.actualizados', 1);

        $this->assertDatabaseCount('items_materiales', 3);
        $this->assertSame($administrador->id, ImportacionCatalogoMaterial::findOrFail($importacionId)->confirmado_por_user_id);

        $this->actingAs($administrador, 'sanctum')
            ->getJson('/api/administracion/materiales/importaciones')
            ->assertOk()
            ->assertJsonPath('data.0.id', $importacionId)
            ->assertJsonPath('data.0.creado_por.id', $administrador->id)
            ->assertJsonPath('data.0.estado', 'confirmada');
    }

    public function test_planilla_con_errores_no_se_confirma_y_camarero_no_puede_importar(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $camarero = User::factory()->create(['rol' => RolUsuario::CamareroMateriales]);
        $contenido = "codigo;nombre;unidad_medida;activo\n".
            "CAJA-01;Caja uno;unidad;si\n".
            "CAJA-01;Caja duplicada;unidad;si\n".
            "FILM-01;Film stretch;rollos;quizas\n";

        $this->actingAs($camarero, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => UploadedFile::fake()->createWithContent('sin_permiso.csv', $contenido),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $importacionId = $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => UploadedFile::fake()->createWithContent('errores.csv', $contenido),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'con_errores')
            ->assertJsonPath('data.resumen.filas_validas', 1)
            ->assertJsonPath('data.resumen.filas_con_error', 2)
            ->json('data.id');

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/materiales/importaciones/{$importacionId}/confirmar")
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');

        $this->assertDatabaseCount('items_materiales', 0);
    }

    public function test_importacion_no_cambia_unidad_de_item_con_folios(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $item = $this->crearItem($administrador);
        $folio = Folio::create([
            'numero_folio' => 'MAT-001',
            'tipo_bulto' => 'material',
            'estado_operacional' => 'disponible',
            'fecha_ingreso' => now(),
            'activo' => true,
            'origen_sistema' => 'manual',
            'estado_integracion' => 'no_vinculado',
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'cantidad_inicial' => 10,
            'cantidad_actual' => 10,
            'cantidad_reservada' => 0,
            'unidad_medida' => 'rollos',
        ]);
        $archivo = UploadedFile::fake()->createWithContent(
            'unidad.csv',
            "codigo;nombre;unidad_medida\nFILM-01;Film stretch;cajas\n",
        );

        $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'con_errores')
            ->assertJsonPath('data.resumen.filas_con_error', 1)
            ->assertJsonFragment([
                'mensaje' => 'La unidad de medida no puede cambiar porque el ítem ya posee folios asociados.',
            ]);
    }

    public function test_confirmacion_rechaza_catalogo_modificado_despues_de_previsualizar(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $item = $this->crearItem($administrador);
        $archivo = UploadedFile::fake()->createWithContent(
            'catalogo.csv',
            "codigo;nombre;unidad_medida\nFILM-01;Film importado;rollos\n",
        );

        $importacionId = $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->json('data.id');

        $item->update(['nombre' => 'Film modificado en otra sesión']);

        $this->actingAs($administrador, 'sanctum')
            ->postJson("/api/administracion/materiales/importaciones/{$importacionId}/confirmar")
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio')
            ->assertJsonPath(
                'message',
                'El catálogo cambió después de la previsualización para el ítem FILM-01. Vuelve a previsualizar la planilla.',
            );

        $this->assertDatabaseHas('items_materiales', [
            'id' => $item->id,
            'nombre' => 'Film modificado en otra sesión',
            'origen_sistema' => 'manual',
        ]);
        $this->assertDatabaseHas('importaciones_catalogo_materiales', [
            'id' => $importacionId,
            'estado' => 'borrador',
            'confirmado_por_user_id' => null,
        ]);
    }

    public function test_previsualizacion_rechaza_planilla_con_mas_de_cinco_mil_filas(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $filas = ["codigo;nombre;unidad_medida"];

        for ($indice = 1; $indice <= 5001; $indice++) {
            $filas[] = "ITEM-{$indice};Material {$indice};unidad";
        }

        $this->actingAs($administrador, 'sanctum')
            ->post('/api/administracion/materiales/importaciones/previsualizar', [
                'archivo' => UploadedFile::fake()->createWithContent('catalogo-grande.csv', implode("\n", $filas)),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio')
            ->assertJsonPath('message', 'La planilla supera el máximo de 5.000 filas permitidas.');

        $this->assertDatabaseCount('importaciones_catalogo_materiales', 0);
        $this->assertDatabaseCount('items_materiales', 0);
    }

    public function test_lector_admite_planilla_xlsx_del_catalogo(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('La extensión ZIP no está disponible.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'materiales-xlsx-');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="inlineStr"><is><t>sku</t></is></c>
      <c r="B1" t="inlineStr"><is><t>formato</t></is></c>
      <c r="C1" t="inlineStr"><is><t>familia</t></is></c>
      <c r="D1" t="inlineStr"><is><t>unidad</t></is></c>
      <c r="E1" t="inlineStr"><is><t>codigo_erp</t></is></c>
      <c r="F1" t="inlineStr"><is><t>estado</t></is></c>
    </row>
    <row r="2">
      <c r="A2" t="inlineStr"><is><t>ETQ-01</t></is></c>
      <c r="B2" t="inlineStr"><is><t>Etiqueta caja 5 kg</t></is></c>
      <c r="C2" t="inlineStr"><is><t>Etiquetas</t></is></c>
      <c r="D2" t="inlineStr"><is><t>unidades</t></is></c>
      <c r="E2" t="inlineStr"><is><t>ERP-ETQ-01</t></is></c>
      <c r="F2" t="inlineStr"><is><t>activo</t></is></c>
    </row>
  </sheetData>
</worksheet>
XML);
        $zip->close();
        $filas = app(LectorPlanillaMaterial::class)->leer(new UploadedFile(
            $ruta,
            'formatos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        ));
        unlink($ruta);

        $this->assertSame('ETQ-01', $filas[0]['codigo']);
        $this->assertSame('Etiqueta caja 5 kg', $filas[0]['nombre']);
        $this->assertSame('Etiquetas', $filas[0]['categoria']);
        $this->assertSame('unidades', $filas[0]['unidad_medida']);
        $this->assertSame('ERP-ETQ-01', $filas[0]['codigo_externo']);
        $this->assertSame('activo', $filas[0]['activo']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearItem(User $usuario, array $datos = []): ItemMaterial
    {
        return ItemMaterial::create([
            'codigo' => 'FILM-01',
            'nombre' => 'Film stretch',
            'categoria' => 'Embalaje',
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $usuario->id,
            'actualizado_por_user_id' => $usuario->id,
            ...$datos,
        ]);
    }
}
