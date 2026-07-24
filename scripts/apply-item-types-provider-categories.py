from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = Path(path)
    text = file.read_text()
    occurrences = text.count(old)
    if occurrences < count:
        raise RuntimeError(f'{path}: expected at least {count} occurrence(s), found {occurrences}: {old[:120]!r}')
    file.write_text(text.replace(old, new, count))


# 1. Oficina: tipo operacional visible y editable.
replace(
    'resources/views/office/materials.blade.php',
    '                                <label><span>Categoría</span><input name="categoria" maxlength="100" placeholder="Cajas"></label>\n                                <label><span>Unidad *</span><input name="unidad_medida" maxlength="40" placeholder="unidades" required></label>',
    '                                <label><span>Categoría comercial</span><input name="categoria" maxlength="100" placeholder="Cajas"></label>\n                                <label><span>Tipo de ítem *</span><select name="categoria_operacional" required><option value="">Selecciona un tipo</option><option value="insumo">Insumo</option><option value="material_mp">Material MP · sin preparar</option><option value="material_pt">Material PT · preparado para línea</option></select></label>\n                                <label><span>Unidad *</span><input name="unidad_medida" maxlength="40" placeholder="unidades" required></label>',
)
replace(
    'resources/views/office/materials.blade.php',
    '                                <label class="materials-check"><input name="activo" type="checkbox" checked><span>Ítem activo</span></label>\n                            </div>',
    '                                <label class="materials-check"><input name="activo" type="checkbox" checked><span>Ítem activo</span></label>\n                                <p class="materials-help materials-wide">El tipo determina si el ítem puede recibirse como insumo o Material MP, o generarse como Material PT mediante una receta. Los ítems sin tipo permanecen fuera de Recepción y Transformación.</p>\n                            </div>',
)
replace(
    'resources/views/office/materials.blade.php',
    'Columnas: temporada_codigo, cliente_codigo, código, nombre, categoría, unidad_medida, código_externo y activo.',
    'Columnas: temporada_codigo, cliente_codigo, código, nombre, categoría, tipo_item, unidad_medida, código_externo y activo.',
)
replace(
    'resources/views/office/materials.blade.php',
    '<th>Nombre</th><th>Unidad</th><th>Acción</th>',
    '<th>Nombre</th><th>Tipo</th><th>Unidad</th><th>Acción</th>',
)

# 2. Oficina JS: categorías comerciales configurables aunque falte tipificación.
replace(
    'resources/js/office-materials.js',
    "function statusText(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }",
    "function statusText(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }\nfunction itemTypeLabel(value) { return ({ insumo: 'Insumo', material_mp: 'Material MP', material_pt: 'Material PT' })[value] || 'Sin tipo operacional'; }",
)
old_provider_assignments = '''function providerCategoryAssignments() {
    const selectedClients = new Set([...elements.providerClientOptions.querySelectorAll('input:checked')].map((input) => input.value));
    const clientCatalogs = new Map(state.clients.map((client) => [client.id, client]));
    const unique = new Map();
    seasonItems().filter((item) => item.activo && item.categoria_operacional && item.categoria?.trim()).forEach((item) => {
        const client = clientCatalogs.get(item.cliente?.id);
        if (!client?.cliente_id || !selectedClients.has(client.cliente_id)) return;
        const category = item.categoria.trim();
        const key = providerCategoryKey(client.cliente_id, category);
        const current = unique.get(key);
        unique.set(key, { key, clientId: client.cliente_id, clientCode: client.codigo, category, count: Number(current?.count || 0) + 1 });
    });
    return [...unique.values()].sort((left, right) => `${left.clientCode} ${left.category}`.localeCompare(`${right.clientCode} ${right.category}`, 'es'));
}'''
new_provider_assignments = '''function providerCategoryAssignments() {
    const selectedClients = new Set([...elements.providerClientOptions.querySelectorAll('input:checked')].map((input) => input.value));
    const clientCatalogs = new Map(state.clients.map((client) => [client.id, client]));
    const unique = new Map();
    seasonItems().filter((item) => item.activo && item.categoria?.trim()).forEach((item) => {
        const client = clientCatalogs.get(item.cliente?.id);
        if (!client?.cliente_id || !selectedClients.has(client.cliente_id)) return;
        const category = item.categoria.trim();
        const key = providerCategoryKey(client.cliente_id, category);
        const current = unique.get(key) || { key, clientId: client.cliente_id, clientCode: client.codigo, category, total: 0, typed: 0 };
        current.total += 1;
        current.typed += Number(Boolean(item.categoria_operacional));
        unique.set(key, current);
    });
    return [...unique.values()].sort((left, right) => `${left.clientCode} ${left.category}`.localeCompare(`${right.clientCode} ${right.category}`, 'es'));
}'''
replace('resources/js/office-materials.js', old_provider_assignments, new_provider_assignments)
replace(
    'resources/js/office-materials.js',
    "    elements.providerCategoryOptions.innerHTML = assignments.map((assignment) => `<label><input name=\"categorias\" type=\"checkbox\" data-key=\"${escapeHtml(assignment.key)}\" data-client-id=\"${assignment.clientId}\" data-category=\"${escapeHtml(assignment.category)}\"${checked.has(assignment.key) ? ' checked' : ''}><span>${escapeHtml(assignment.clientCode)} · ${escapeHtml(assignment.category)} · ${assignment.count} ${assignment.count === 1 ? 'ítem' : 'ítems'}</span></label>`).join('') || '<p class=\"empty-state\">Selecciona un cliente con categorías activas en la temporada elegida.</p>';",
    "    elements.providerCategoryOptions.innerHTML = assignments.map((assignment) => { const pending = assignment.total - assignment.typed; const detail = pending > 0 ? `${assignment.total} ítems · ${assignment.typed} tipificados · ${pending} pendientes` : `${assignment.total} ${assignment.total === 1 ? 'ítem tipificado' : 'ítems tipificados'}`; return `<label><input name=\"categorias\" type=\"checkbox\" data-key=\"${escapeHtml(assignment.key)}\" data-client-id=\"${assignment.clientId}\" data-category=\"${escapeHtml(assignment.category)}\"${checked.has(assignment.key) ? ' checked' : ''}><span>${escapeHtml(assignment.clientCode)} · ${escapeHtml(assignment.category)} · ${escapeHtml(detail)}</span></label>`; }).join('') || '<p class=\"empty-state\">El cliente seleccionado no posee categorías comerciales activas en esta temporada.</p>';",
)
replace(
    'resources/js/office-materials.js',
    "<small>${escapeHtml(item.categoria || 'Sin categoría')} · ${escapeHtml(item.unidad_medida)} · ${item.folios_activos} folios activos</small>",
    "<small>${escapeHtml(item.categoria || 'Sin categoría comercial')} · ${escapeHtml(itemTypeLabel(item.categoria_operacional))} · ${escapeHtml(item.unidad_medida)} · ${item.folios_activos} folios activos</small>",
)
replace(
    'resources/js/office-materials.js',
    "for (const field of ['id', 'codigo', 'nombre', 'categoria', 'unidad_medida', 'codigo_externo'])",
    "for (const field of ['id', 'codigo', 'nombre', 'categoria', 'categoria_operacional', 'unidad_medida', 'codigo_externo'])",
)
replace(
    'resources/js/office-materials.js',
    "const content = '\\uFEFFtemporada_codigo;cliente_codigo;codigo;nombre;categoria;unidad_medida;codigo_externo;activo\\n2026-2027;AG-001;CAJ-5KG;Caja cartón 5 kg;Cajas;unidad;ERP-1054;si\\n';",
    "const content = '\\uFEFFtemporada_codigo;cliente_codigo;codigo;nombre;categoria;tipo_item;unidad_medida;codigo_externo;activo\\n2026-2027;AG-001;CAJ-5KG;Caja cartón 5 kg;Cajas;material_mp;unidad;ERP-1054;si\\n';",
)
replace(
    'resources/js/office-materials.js',
    "<td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(row.unidad_medida)}</td><td><span class=\"material-import-action\">${escapeHtml(statusText(row.accion))}</span></td>",
    "<td>${escapeHtml(row.nombre)}</td><td>${escapeHtml(itemTypeLabel(row.categoria_operacional))}</td><td>${escapeHtml(row.unidad_medida)}</td><td><span class=\"material-import-action\">${escapeHtml(statusText(row.accion))}</span></td>",
)
replace(
    'resources/js/office-materials.js',
    "|| '<tr><td colspan=\"7\">No existen filas válidas para mostrar.</td></tr>';",
    "|| '<tr><td colspan=\"8\">No existen filas válidas para mostrar.</td></tr>';",
)

# 3. Validación manual: todo ítem nuevo o editado debe declarar su tipo.
replace(
    'app/Http/Requests/GuardarItemMaterialRequest.php',
    "            'categoria_operacional' => [\n                'nullable',\n                Rule::enum(CategoriaOperacionalMaterial::class),\n            ],",
    "            'categoria_operacional' => [\n                'required',\n                Rule::enum(CategoriaOperacionalMaterial::class),\n            ],",
)
replace(
    'app/Http/Requests/GuardarItemMaterialRequest.php',
    "    protected function prepareForValidation(): void",
    "    /** @return array<string, string> */\n    public function messages(): array\n    {\n        return [\n            'categoria_operacional.required' => 'Selecciona el tipo de ítem.',\n            'categoria_operacional.enum' => 'El tipo de ítem debe ser Insumo, Material MP o Material PT.',\n        ];\n    }\n\n    protected function prepareForValidation(): void",
)

# 4. Proveedor: la categoría comercial puede configurarse antes de terminar la clasificación.
replace(
    'app/Http/Requests/GuardarProveedorMaterialRequest.php',
    "                ->whereNotNull('categoria')\n                ->whereNotNull('categoria_operacional')",
    "                ->whereNotNull('categoria')",
)
replace(
    'app/Http/Requests/GuardarProveedorMaterialRequest.php',
    "La categoría no posee ítems operacionales activos para el cliente.",
    "La categoría no posee ítems activos para el cliente.",
)
replace(
    'app/Http/Requests/GuardarProveedorMaterialRequest.php',
    "    protected function prepareForValidation(): void",
    "    /** @return array<string, string> */\n    public function messages(): array\n    {\n        return [\n            'categorias.required' => 'Selecciona al menos una categoría habilitada.',\n            'categorias.min' => 'Selecciona al menos una categoría habilitada.',\n        ];\n    }\n\n    protected function prepareForValidation(): void",
)

# 5. Carga masiva: columna tipo_item obligatoria y actualizable.
replace(
    'app/Services/Materiales/LectorPlanillaMaterial.php',
    "            'categoria', 'familia', 'grupo' => 'categoria',",
    "            'categoria', 'familia', 'grupo' => 'categoria',\n            'tipo_item', 'tipo_material', 'categoria_operacional', 'clasificacion_operacional' => 'categoria_operacional',",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "namespace App\\Services\\Materiales;\n\nuse App\\Models\\ClienteMaterial;",
    "namespace App\\Services\\Materiales;\n\nuse App\\Enums\\CategoriaOperacionalMaterial;\nuse App\\Models\\ClienteMaterial;",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "            'categoria' => $this->opcional($fila['categoria'] ?? ''),\n            'unidad_medida' => mb_strtolower($this->texto($fila['unidad_medida'] ?? '')),",
    "            'categoria' => $this->opcional($fila['categoria'] ?? ''),\n            'categoria_operacional' => $this->categoriaOperacional($fila['categoria_operacional'] ?? ''),\n            'categoria_operacional_original' => $this->texto($fila['categoria_operacional'] ?? ''),\n            'unidad_medida' => mb_strtolower($this->texto($fila['unidad_medida'] ?? '')),",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "        if (mb_strlen((string) ($fila['categoria'] ?? '')) > 100) {\n            $errores[] = 'La categoría admite hasta 100 caracteres.';\n        }\n        if ($fila['unidad_medida'] === ''",
    "        if (mb_strlen((string) ($fila['categoria'] ?? '')) > 100) {\n            $errores[] = 'La categoría admite hasta 100 caracteres.';\n        }\n        if ($fila['categoria_operacional_original'] === '') {\n            $errores[] = 'Falta el tipo de ítem.';\n        } elseif ($fila['categoria_operacional'] === null) {\n            $errores[] = 'El tipo de ítem debe ser insumo, material_mp o material_pt.';\n        }\n        if ($fila['unidad_medida'] === ''",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "                $datos = [\n                    'nombre' => $fila['nombre'],\n                    'unidad_medida' => $fila['unidad_medida'],\n                ];",
    "                $datos = [\n                    'nombre' => $fila['nombre'],\n                    'categoria_operacional' => $fila['categoria_operacional'],\n                    'unidad_medida' => $fila['unidad_medida'],\n                ];",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "            || (($fila['categoria'] ?? null) !== null && $existente->categoria !== $fila['categoria'])\n            || (($fila['codigo_externo'] ?? null) !== null",
    "            || (($fila['categoria'] ?? null) !== null && $existente->categoria !== $fila['categoria'])\n            || $existente->categoria_operacional?->value !== $fila['categoria_operacional']\n            || (($fila['codigo_externo'] ?? null) !== null",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "            'categoria' => $item->categoria,\n            'unidad_medida' => $item->unidad_medida,",
    "            'categoria' => $item->categoria,\n            'categoria_operacional' => $item->categoria_operacional?->value,\n            'unidad_medida' => $item->unidad_medida,",
)
replace(
    'app/Services/Materiales/ServicioImportacionCatalogoMaterial.php',
    "    private function activo(mixed $valor): ?bool",
    "    private function categoriaOperacional(mixed $valor): ?string\n    {\n        $texto = Str::of((string) $valor)\n            ->ascii()\n            ->lower()\n            ->replaceMatches('/[^a-z0-9]+/', '_')\n            ->trim('_')\n            ->toString();\n\n        return match ($texto) {\n            CategoriaOperacionalMaterial::Insumo->value => CategoriaOperacionalMaterial::Insumo->value,\n            'mp', 'material_mp', 'material_mp_sin_preparar', 'material_sin_preparar', 'material_de_embalaje_sin_preparar' => CategoriaOperacionalMaterial::MaterialMp->value,\n            'pt', 'material_pt', 'material_pt_preparado_para_linea', 'material_preparado', 'material_preparado_para_linea' => CategoriaOperacionalMaterial::MaterialPt->value,\n            default => null,\n        };\n    }\n\n    private function activo(mixed $valor): ?bool",
)

# 6. Pruebas de interfaz existentes.
replace(
    'tests/Feature/InterfazCategoriasProveedorMaterialTest.php',
    "            ->assertSee('Categorías habilitadas', false);",
    "            ->assertSee('Categorías habilitadas', false)\n            ->assertSee('name=\"categoria_operacional\"', false)\n            ->assertSee('Material MP · sin preparar', false)\n            ->assertSee('Material PT · preparado para línea', false);",
)
replace(
    'tests/Feature/InterfazCategoriasProveedorMaterialTest.php',
    "        $this->assertStringContainsString('Proveedor, clientes y categorías actualizados.', $office);",
    "        $this->assertStringContainsString('Proveedor, clientes y categorías actualizados.', $office);\n        $this->assertStringContainsString('tipificados', $office);\n        $this->assertStringContainsString('categoria_operacional', $office);\n        $this->assertStringContainsString('tipo_item', $office);",
)

# 7. Nueva cobertura API para clasificación y configuración previa del proveedor.
Path('tests/Feature/Api/ClasificacionItemMaterialApiTest.php').write_text(r'''<?php

namespace Tests\Feature\Api;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\RolUsuario;
use App\Models\ClienteMaterial;
use App\Models\ItemMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClasificacionItemMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_operacional_es_obligatorio_y_puede_agregarse_a_un_item_existente(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $cliente = ClienteMaterial::query()->where('codigo', 'GENERAL')->firstOrFail();

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/materiales/items', [
                'cliente_material_id' => $cliente->id,
                'codigo' => 'SIN-TIPO-NUEVO',
                'nombre' => 'Material sin clasificación',
                'categoria' => 'Cajas',
                'unidad_medida' => 'unidades',
                'activo' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categoria_operacional');

        $item = ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => 'EXISTENTE-SIN-TIPO',
            'nombre' => 'Caja existente sin tipo',
            'categoria' => 'Cajas',
            'categoria_operacional' => null,
            'unidad_medida' => 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->putJson("/api/administracion/materiales/items/{$item->id}", [
                'cliente_material_id' => $cliente->id,
                'codigo' => $item->codigo,
                'nombre' => $item->nombre,
                'categoria' => $item->categoria,
                'categoria_operacional' => CategoriaOperacionalMaterial::MaterialMp->value,
                'unidad_medida' => $item->unidad_medida,
                'codigo_externo' => null,
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.categoria_operacional', 'material_mp')
            ->assertJsonPath('data.categoria_operacional_etiqueta', 'Material de embalaje sin preparar');

        $this->assertDatabaseHas('items_materiales', [
            'id' => $item->id,
            'categoria_operacional' => 'material_mp',
        ]);
    }

    public function test_proveedor_puede_configurar_categoria_comercial_antes_de_tipificar_sus_items(): void
    {
        $administrador = User::factory()->create(['rol' => RolUsuario::Administrador]);
        $cliente = ClienteMaterial::query()->with('cliente')->where('codigo', 'GENERAL')->firstOrFail();
        ItemMaterial::create([
            'cliente_material_id' => $cliente->id,
            'codigo' => 'CAJA-PENDIENTE-TIPO',
            'nombre' => 'Caja pendiente de clasificación',
            'categoria' => 'Cajas',
            'categoria_operacional' => null,
            'unidad_medida' => 'unidades',
            'origen_sistema' => 'manual',
            'activo' => true,
            'creado_por_user_id' => $administrador->id,
            'actualizado_por_user_id' => $administrador->id,
        ]);

        $this->actingAs($administrador, 'sanctum')
            ->postJson('/api/administracion/materiales/proveedores', [
                'codigo' => 'PRV-PENDIENTE',
                'nombre' => 'Proveedor pendiente de clasificación',
                'codigo_externo' => null,
                'activo' => true,
                'cliente_ids' => [$cliente->cliente_id],
                'categorias' => [[
                    'cliente_id' => $cliente->cliente_id,
                    'categoria' => 'Cajas',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.categorias.0.categoria', 'Cajas');
    }
}
''')

# 8. Documentación operacional.
doc = Path('docs/PROVEEDORES_CATEGORIAS_MATERIALES.md')
text = doc.read_text()
text += '''

## Clasificación operacional de los ítems

La categoría comercial y el tipo de ítem cumplen funciones diferentes:

- `categoria`: familia comercial utilizada para habilitar proveedores, por ejemplo `ABSORPAD`, `CAJAS` o `ETIQUETAS`;
- `categoria_operacional`: comportamiento logístico del ítem: `insumo`, `material_mp` o `material_pt`.

Una categoría comercial puede asociarse al proveedor aunque algunos de sus ítems todavía estén pendientes de tipificación. La oficina muestra cuántos están tipificados y cuántos pendientes. Sin embargo, un ítem sin tipo operacional no se ofrece en Recepción ni puede participar en una receta.

Los ítems existentes pueden editarse para agregar el tipo. La carga masiva acepta la columna `tipo_item` y los valores `insumo`, `material_mp` y `material_pt`.
'''
doc.write_text(text)
