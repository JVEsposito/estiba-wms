from pathlib import Path

path = Path('.github/scripts/apply-camera-only-1.py')
text = path.read_text(encoding='utf-8')
old = '''regex(
    'app/Http/Controllers/Api/MovimientoController.php',
    r"        if \\(\\$folio->ubicacionActual\\) \\{.*?\\n        \\}\\n\\n        if \\(\\$folio->tipo_bulto === TipoBulto::Material",
    """        if ($folio->ubicacionActual) {
            $ubicacion = $folio->ubicacionActual;
            $posicion = $ubicacion->posicion;
            $camara = $ubicacion->camara ?? $posicion?->camara;

            if ($folio->tipo_bulto === TipoBulto::Material && $camara && ! $posicion) {
                return [
                    true,
                    \\"El folio está en {$camara->codigo} sin posición. Puede completar una ubicación exacta.\\",
                ];
            }

            $detalle = $posicion?->etiqueta ? \\" · {$posicion->etiqueta}\\" : '';

            return [
                false,
                \\"El folio ya está ubicado en {$camara?->codigo}{$detalle}.\\",
            ];
        }

        if ($folio->tipo_bulto === TipoBulto::Material""",
)
'''
new = '''replace(
    'app/Http/Controllers/Api/MovimientoController.php',
    """        if ($folio->ubicacionActual) {
            $posicion = $folio->ubicacionActual->posicion;

            return [
                false,
                \\"El folio ya está ubicado en {$posicion->camara->codigo} · {$posicion->etiqueta}.\\",
            ];
        }
""",
    """        if ($folio->ubicacionActual) {
            $ubicacion = $folio->ubicacionActual;
            $posicion = $ubicacion->posicion;
            $camara = $ubicacion->camara ?? $posicion?->camara;

            if ($folio->tipo_bulto === TipoBulto::Material && $camara && ! $posicion) {
                return [
                    true,
                    \\"El folio está en {$camara->codigo} sin posición. Puede completar una ubicación exacta.\\",
                ];
            }

            $detalle = $posicion?->etiqueta ? \\" · {$posicion->etiqueta}\\" : '';

            return [
                false,
                \\"El folio ya está ubicado en {$camara?->codigo}{$detalle}.\\",
            ];
        }
""",
)
'''
if old not in text:
    raise RuntimeError('No se encontró el bloque defectuoso del aplicador')
path.write_text(text.replace(old, new, 1), encoding='utf-8')
