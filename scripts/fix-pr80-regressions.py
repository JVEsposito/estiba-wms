from pathlib import Path


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = Path(path)
    text = file.read_text()
    if text.count(old) < count:
        raise RuntimeError(f'{path}: fragment not found: {old[:120]!r}')
    file.write_text(text.replace(old, new, count))


# La categoría comercial puede autorizarse antes de completar la tipificación.
replace(
    'tests/Feature/Api/AdministracionAccesoApiTest.php',
    '    public function test_proveedor_rechaza_categorias_sin_items_operacionales_y_entradas_malformadas(): void',
    '    public function test_proveedor_admite_categoria_comercial_pendiente_de_tipificacion_y_rechaza_entradas_malformadas(): void',
)
replace(
    'tests/Feature/Api/AdministracionAccesoApiTest.php',
    "        $this->actingAs($administrador, 'sanctum')\n            ->postJson('/api/administracion/materiales/proveedores', $payload)\n            ->assertUnprocessable()\n            ->assertJsonValidationErrors(['categorias.0.categoria']);\n\n        $payload['categorias'] = ['entrada-malformada'];",
    "        $this->actingAs($administrador, 'sanctum')\n            ->postJson('/api/administracion/materiales/proveedores', $payload)\n            ->assertCreated()\n            ->assertJsonPath('data.categorias.0.categoria', 'Solo no operacional');\n\n        $payload['codigo'] = 'PROV-MALFORMADO';\n        $payload['categorias'] = ['entrada-malformada'];",
)

# Los ítems creados por API deben declarar el tipo operacional.
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    "                'categoria' => 'Embalaje',\n                'unidad_medida' => 'ROLLOS',",
    "                'categoria' => 'Embalaje',\n                'categoria_operacional' => 'insumo',\n                'unidad_medida' => 'ROLLOS',",
)
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    "                    'nombre' => 'Caja cartón 5 kg',\n                    'unidad_medida' => 'unidades',",
    "                    'nombre' => 'Caja cartón 5 kg',\n                    'categoria_operacional' => 'material_mp',\n                    'unidad_medida' => 'unidades',",
)
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    "                'nombre' => 'Caja duplicada',\n                'unidad_medida' => 'unidades',",
    "                'nombre' => 'Caja duplicada',\n                'categoria_operacional' => 'material_mp',\n                'unidad_medida' => 'unidades',",
)
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    "                'nombre' => 'Caja temporada nueva',\n                'unidad_medida' => 'unidades',",
    "                'nombre' => 'Caja temporada nueva',\n                'categoria_operacional' => 'material_mp',\n                'unidad_medida' => 'unidades',",
)
