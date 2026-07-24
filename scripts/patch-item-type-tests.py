from pathlib import Path

path = Path('tests/Feature/Api/ImportacionCatalogoMaterialApiTest.php')
text = path.read_text()

text = text.replace(
    'namespace Tests\\Feature\\Api;\n\nuse App\\Enums\\RolUsuario;',
    'namespace Tests\\Feature\\Api;\n\nuse App\\Enums\\CategoriaOperacionalMaterial;\nuse App\\Enums\\RolUsuario;',
    1,
)

replacements = {
    'temporada_codigo;cliente_codigo;codigo;nombre;categoria;unidad_medida;codigo_externo;activo':
        'temporada_codigo;cliente_codigo;codigo;nombre;categoria;tipo_item;unidad_medida;codigo_externo;activo',
    'GENERAL;GENERAL;FILM-01;Film stretch reforzado;Embalaje;ROLLOS;;si':
        'GENERAL;GENERAL;FILM-01;Film stretch reforzado;Embalaje;insumo;ROLLOS;;si',
    'GENERAL;GENERAL;CAJ-5KG;Caja cartón 5 kg;Cajas;UNIDAD;ERP-CAJ-5;si':
        'GENERAL;GENERAL;CAJ-5KG;Caja cartón 5 kg;Cajas;material_mp;UNIDAD;ERP-CAJ-5;si',
    'temporada_codigo;cliente_codigo;codigo;nombre;unidad_medida;activo':
        'temporada_codigo;cliente_codigo;codigo;nombre;tipo_item;unidad_medida;activo',
    'GENERAL;GENERAL;CAJA-01;Caja uno;unidad;si':
        'GENERAL;GENERAL;CAJA-01;Caja uno;material_mp;unidad;si',
    'GENERAL;GENERAL;CAJA-01;Caja duplicada;unidad;si':
        'GENERAL;GENERAL;CAJA-01;Caja duplicada;material_mp;unidad;si',
    'GENERAL;GENERAL;FILM-01;Film stretch;rollos;quizas':
        'GENERAL;GENERAL;FILM-01;Film stretch;insumo;rollos;quizas',
    'temporada_codigo;cliente_codigo;codigo;nombre;unidad_medida\\n':
        'temporada_codigo;cliente_codigo;codigo;nombre;tipo_item;unidad_medida\\n',
    'GENERAL;CLI-NORTE;CAJA-5KG;Caja cliente norte;unidades\\n':
        'GENERAL;CLI-NORTE;CAJA-5KG;Caja cliente norte;material_mp;unidades\\n',
    'GENERAL;CLI-SUR;CAJA-5KG;Caja cliente sur;unidades\\n':
        'GENERAL;CLI-SUR;CAJA-5KG;Caja cliente sur;material_mp;unidades\\n',
    'GENERAL;GENERAL;FILM-01;Film stretch;cajas\\n':
        'GENERAL;GENERAL;FILM-01;Film stretch;insumo;cajas\\n',
    'GENERAL;GENERAL;FILM-01;Film importado;rollos\\n':
        'GENERAL;GENERAL;FILM-01;Film importado;insumo;rollos\\n',
    "['temporada_codigo;cliente_codigo;codigo;nombre;unidad_medida']":
        "['temporada_codigo;cliente_codigo;codigo;nombre;tipo_item;unidad_medida']",
    '"GENERAL;GENERAL;ITEM-{$indice};Material {$indice};unidad"':
        '"GENERAL;GENERAL;ITEM-{$indice};Material {$indice};insumo;unidad"',
}

for old, new in replacements.items():
    if old not in text:
        raise RuntimeError(f'Missing expected import test fragment: {old}')
    text = text.replace(old, new)

text = text.replace(
    "            'categoria' => 'Embalaje',\n            'unidad_medida' => 'rollos',",
    "            'categoria' => 'Embalaje',\n            'categoria_operacional' => CategoriaOperacionalMaterial::Insumo,\n            'unidad_medida' => 'rollos',",
    1,
)

text = text.replace(
    "            'unidad_medida' => 'rollos',\n            'activo' => true,\n            'origen_sistema' => 'importacion_catalogo',",
    "            'unidad_medida' => 'rollos',\n            'categoria_operacional' => 'insumo',\n            'activo' => true,\n            'origen_sistema' => 'importacion_catalogo',",
    1,
)
text = text.replace(
    "            'unidad_medida' => 'unidad',\n            'codigo_externo' => 'ERP-CAJ-5',",
    "            'unidad_medida' => 'unidad',\n            'categoria_operacional' => 'material_mp',\n            'codigo_externo' => 'ERP-CAJ-5',",
    1,
)

path.write_text(text)
