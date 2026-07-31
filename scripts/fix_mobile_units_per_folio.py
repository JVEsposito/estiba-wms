from pathlib import Path

path = Path('mobile/src/components/MaterialTransformationOperation.tsx')
content = path.read_text(encoding='utf-8')
old = 'formatQuantity(unitsPerOutputFolio)'
new = 'formatQuantity(String(unitsPerOutputFolio))'

if content.count(old) != 1:
    raise RuntimeError(f'Se esperaba una coincidencia y se encontraron {content.count(old)}.')

path.write_text(content.replace(old, new), encoding='utf-8')
print('Conversión de unidades por folio corregida.')
