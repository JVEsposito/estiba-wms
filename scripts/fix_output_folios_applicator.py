from pathlib import Path

path = Path('scripts/apply_output_folios.py')
content = path.read_text(encoding='utf-8')
start_marker = '''replace(
    "mobile/src/domain/materialTransformation.ts",
    "  cantidad_planificada_salida: string;\\n  cantidad_real_salida: string | null;\\n",'''
start = content.index(start_marker)
end = content.index('\n\n# PDA: siguiente pallet sugerido', start)
replacement = '''replace(
    "mobile/src/domain/materialTransformation.ts",
    "export type MaterialTransformationOrder = {\\n"
    "  id: string;\\n"
    "  estado: MaterialTransformationState;\\n"
    "  version: number;\\n"
    "  cantidad_planificada_salida: string;\\n"
    "  cantidad_real_salida: string | null;\\n",
    "export type MaterialTransformationOrder = {\\n"
    "  id: string;\\n"
    "  estado: MaterialTransformationState;\\n"
    "  version: number;\\n"
    "  cantidad_planificada_salida: string;\\n"
    "  cantidad_real_salida: string | null;\\n"
    "  unidades_por_folio_salida: string | null;\\n"
    "  folios_planificados: number | null;\\n"
    "  folios_generados?: number;\\n"
    "  folios_pendientes?: number;\\n",
)'''
path.write_text(content[:start] + replacement + content[end:], encoding='utf-8')
print('Aplicador ajustado para modificar solo MaterialTransformationOrder.')
