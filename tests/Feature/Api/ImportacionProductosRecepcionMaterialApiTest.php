<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteMaterial;
use App\Models\ItemMaterial;
use App\Models\ProveedorMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ImportacionProductosRecepcionMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_previsualiza_xlsx_y_distribuye_la_cantidad_en_bultos_sin_guardar_recepcion(): void
    {
        [$administrador, $cliente, $proveedor] = $this->prepararCatalogo();
        $archivo = $this->crearXlsx([
            [
                'codigo_item',
                'cantidad_documental',
                'cantidad_contada',
                'cantidad_aceptada',
                'cantidad_rechazada',
                'unidades_por_bulto',
                'lote_proveedor',
                'fecha_fabricacion',
                'fecha_vencimiento',
                'bloqueado',
                'motivo_bloqueo',
                'observacion',
            ],
            [
                'FILM-REC',
                '500',
                '500',
                '500',
                '0',
                '60',
                'LOTE-001',
                '01/08/2026',
                '01/08/2027',
                'no',
                '',
                'Carga desde Excel',
            ],
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->post('/api/materiales/recepciones/importaciones/previsualizar', [
                'archivo' => $archivo,
                'cliente_id' => $cliente->id,
                'proveedor_material_id' => $proveedor->id,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.resumen.filas_leidas', 1)
            ->assertJsonPath('data.resumen.filas_validas', 1)
            ->assertJsonPath('data.resumen.filas_con_error', 0)
            ->assertJsonPath('data.resumen.folios_estimados', 9)
            ->assertJsonPath('data.filas.0.item.codigo', 'FILM-REC')
            ->assertJsonPath('data.filas.0.cantidad_aceptada', 500)
            ->assertJsonCount(9, 'data.filas.0.bultos')
            ->assertJsonPath('data.filas.0.bultos.0.cantidad', 60)
            ->assertJsonPath('data.filas.0.bultos.8.cantidad', 20)
            ->assertJsonPath('data.filas.0.bultos.0.fecha_fabricacion', '2026-08-01')
            ->assertJsonPath('data.filas.0.bultos.0.fecha_vencimiento', '2027-08-01');

        $this->assertDatabaseCount('recepciones_materiales', 0);
        $this->assertDatabaseCount('detalles_recepciones_materiales', 0);
        $this->assertDatabaseCount('bultos_recepciones_materiales', 0);
        $this->assertDatabaseCount('folios', 0);
    }

    public function test_informa_todas_las_filas_invalidas_y_no_permite_una_carga_parcial(): void
    {
        [$administrador, $cliente, $proveedor] = $this->prepararCatalogo();
        $contenido = "codigo_item;cantidad_aceptada;cantidad_rechazada;cantidad_contada;unidades_por_bulto;bloqueado;motivo_bloqueo\n".
            "DESCONOCIDO;100;0;100;50;no;\n".
            "FILM-REC;100;10;100;50;si;\n";

        $this->actingAs($administrador, 'sanctum')
            ->post('/api/materiales/recepciones/importaciones/previsualizar', [
                'archivo' => UploadedFile::fake()->createWithContent('recepcion-errores.csv', $contenido),
                'cliente_id' => $cliente->id,
                'proveedor_material_id' => $proveedor->id,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.resumen.filas_leidas', 2)
            ->assertJsonPath('data.resumen.filas_validas', 0)
            ->assertJsonPath('data.resumen.filas_con_error', 2)
            ->assertJsonPath('data.resumen.folios_estimados', 0)
            ->assertJsonCount(0, 'data.filas')
            ->assertJsonCount(2, 'data.errores')
            ->assertJsonFragment([
                'mensaje' => 'El ítem no existe para el cliente seleccionado.',
            ])
            ->assertJsonFragment([
                'mensaje' => 'La cantidad contada debe coincidir con aceptada más rechazada. Un producto bloqueado debe indicar el motivo del bloqueo.',
            ]);

        $this->assertDatabaseCount('recepciones_materiales', 0);
        $this->assertDatabaseCount('folios', 0);
    }

    public function test_usuario_sin_permiso_no_puede_previsualizar_recepciones_masivas(): void
    {
        [, $cliente, $proveedor] = $this->prepararCatalogo();
        $consulta = User::factory()->create([
            'rol' => RolUsuario::Consulta,
            'activo' => true,
        ]);
        $archivo = UploadedFile::fake()->createWithContent(
            'recepcion.csv',
            "codigo_item;cantidad_aceptada;unidades_por_bulto\nFILM-REC;10;10\n",
        );

        $this->actingAs($consulta, 'sanctum')
            ->post('/api/materiales/recepciones/importaciones/previsualizar', [
                'archivo' => $archivo,
                'cliente_id' => $cliente->id,
                'proveedor_material_id' => $proveedor->id,
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /**
     * @return array{User, \App\Models\Cliente, ProveedorMaterial}
     */
    private function prepararCatalogo(): array
    {
        $administrador = User::factory()->create([
            'rol' => RolUsuario::Administrador,
            'activo' => true,
        ]);
        $catalogo = ClienteMaterial::query()
            ->with(['cliente', 'temporada'])
            ->where('codigo', 'GENERAL')
            ->whereHas('temporada', fn ($consulta) => $consulta->where('activa', true))
            ->firstOrFail();
        $cliente = $catalogo->cliente;
        $cliente->update(['codigo_folio_materiales' => 'GE']);
        ItemMaterial::create([
            'cliente_material_id' => $catalogo->id,
            'codigo' => 'FILM-REC',
            'nombre' => 'Film para recepción',
            'categoria' => 'Embalaje',
            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,
            'unidad_medida' => 'rollos',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        $proveedor = ProveedorMaterial::create([
            'codigo' => 'PROV-REC',
            'nombre' => 'Proveedor recepción',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);
        DB::table('clientes_proveedores_materiales')->insert([
            'id' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'proveedor_material_id' => $proveedor->id,
            'activo' => true,
            'categorias' => json_encode(['Embalaje'], JSON_UNESCAPED_UNICODE),
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$administrador, $cliente, $proveedor];
    }

    /**
     * @param  array<int, array<int, string>>  $filas
     */
    private function crearXlsx(array $filas): UploadedFile
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('La extensión ZIP no está disponible.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'recepcion-material-xlsx-');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $filasXml = collect($filas)->map(function (array $fila, int $indiceFila): string {
            $numeroFila = $indiceFila + 1;
            $celdas = collect($fila)->map(function (string $valor, int $indiceColumna) use ($numeroFila): string {
                $columna = chr(ord('A') + $indiceColumna);
                $texto = htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                return "<c r=\"{$columna}{$numeroFila}\" t=\"inlineStr\"><is><t>{$texto}</t></is></c>";
            })->implode('');

            return "<row r=\"{$numeroFila}\">{$celdas}</row>";
        })->implode('');
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>{$filasXml}</sheetData>
</worksheet>
XML);
        $zip->close();

        return new UploadedFile(
            $ruta,
            'recepcion-materiales.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
