from pathlib import Path

path = Path('tests/Feature/Api/TransformacionMaterialApiTest.php')
content = path.read_text(encoding='utf-8')
old = """    private function prepararOrdenOperacional(
        float $cantidadPlanificada,
        ?float $unidadesPorFolio = null,
    ): array {
"""
new = """    private function prepararOrdenOperacional(
        float $cantidadPlanificada,
        ?float $unidadesPorFolio = null,
    ): array
    {
"""

if content.count(old) != 1:
    raise RuntimeError(f'Se esperaba una coincidencia y se encontraron {content.count(old)}.')

path.write_text(content.replace(old, new), encoding='utf-8')
print('Formato de prepararOrdenOperacional corregido.')
