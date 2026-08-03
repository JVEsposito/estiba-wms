from pathlib import Path

path = Path('tests/Feature/Api/CustodiaDistribuidaMaterialesTest.php')
content = path.read_text(encoding='utf-8')
old = "        $this->assertSame('PACK-01', $filasExportacion[0]['codigo_almacen']);"
new = "        $this->assertSame($destino->codigo, $filasExportacion[0]['codigo_almacen']);"
if content.count(old) != 1:
    raise RuntimeError(f'Se esperaba una coincidencia, encontradas: {content.count(old)}')
path.write_text(content.replace(old, new, 1), encoding='utf-8')
