from pathlib import Path
import re


def read(path):
    return Path(path).read_text(encoding='utf-8')


def write(path, content):
    Path(path).write_text(content, encoding='utf-8')


def replace(path, old, new):
    text = read(path)
    if old not in text:
        raise RuntimeError(f'No se encontró patrón en {path}: {old[:100]!r}')
    write(path, text.replace(old, new, 1))


def regex(path, pattern, replacement):
    text = read(path)
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f'Regex {count} en {path}: {pattern[:100]!r}')
    write(path, updated)


replace(
    'app/Services/Materiales/ServicioReservaFifoMaterial.php',
    'use App\\Enums\\EstadoOperacionalFolio;\n',
    'use App\\Enums\\EstadoCamara;\nuse App\\Enums\\EstadoOperacionalFolio;\nuse App\\Enums\\EstadoPosicion;\n',
)
replace(
    'app/Services/Materiales/ServicioReservaFifoMaterial.php',
    """            ->whereHas('folio.ubicacionActual.posicion.camara', fn ($consulta) => $consulta
                ->where('contenido', ContenidoCamara::Materiales->value))
""",
    """            ->whereHas('folio.ubicacionActual', fn ($consulta) => $consulta
                ->whereHas('camara', fn ($camaras) => $camaras
                    ->where('contenido', ContenidoCamara::Materiales->value)
                    ->where('estado', EstadoCamara::Activa->value))
                ->where(fn ($ubicaciones) => $ubicaciones
                    ->whereNull('posicion_id')
                    ->orWhereHas('posicion', fn ($posiciones) => $posiciones
                        ->where('estado', EstadoPosicion::Activa->value))))
""",
)

replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    "                'folio.ubicacionActual.posicion.camara',\n",
    "                'folio.ubicacionActual.camara',\n                'folio.ubicacionActual.posicion',\n",
)
replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    """                        ->orWhereHas('folio.ubicacionActual.posicion', fn (EloquentBuilder $posiciones) => $posiciones
                            ->where('etiqueta', 'like', $termino)
                            ->orWhereHas('camara', fn (EloquentBuilder $camaras) => $camaras
                                ->where('codigo', 'like', $termino)
                                ->orWhere('nombre', 'like', $termino)));
""",
    """                        ->orWhereHas('folio.ubicacionActual.posicion', fn (EloquentBuilder $posiciones) => $posiciones
                            ->where('etiqueta', 'like', $termino))
                        ->orWhereHas('folio.ubicacionActual.camara', fn (EloquentBuilder $camaras) => $camaras
                            ->where('codigo', 'like', $termino)
                            ->orWhere('nombre', 'like', $termino));
""",
)
replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    """                    ->orWhereHas('folio.ubicacionActual.posicion.camara', fn (EloquentBuilder $camaras) => $camaras
                        ->where('contenido', ContenidoCamara::Materiales->value));
""",
    """                    ->orWhereHas('folio.ubicacionActual.camara', fn (EloquentBuilder $camaras) => $camaras
                        ->where('contenido', ContenidoCamara::Materiales->value));
""",
)
replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    """            ->leftJoin('posiciones as p', 'p.id', '=', 'ua.posicion_id')
            ->leftJoin('camaras as ca', 'ca.id', '=', 'p.camara_id')
""",
    """            ->leftJoin('posiciones as p', 'p.id', '=', 'ua.posicion_id')
            ->leftJoin('camaras as ca', 'ca.id', '=', 'ua.camara_id')
""",
)
regex(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    r"    private function expresionDisponible\(\): array\n    \{.*?\n    \}\n\n    private function serializar",
    '''    private function expresionDisponible(): array
    {
        return [
            'CASE WHEN ua.id IS NOT NULL'
                .' AND ca.contenido = ?'
                .' AND ca.estado = ?'
                .' AND (ua.posicion_id IS NULL OR p.estado = ?)'
                .' AND f.estado_operacional = ?'
                .' AND fm.motivo_bloqueo IS NULL'
                .' THEN CASE WHEN fm.cantidad_actual > fm.cantidad_reservada'
                .' THEN fm.cantidad_actual - fm.cantidad_reservada ELSE 0 END'
                .' ELSE 0 END',
            [
                ContenidoCamara::Materiales->value,
                EstadoCamara::Activa->value,
                EstadoPosicion::Activa->value,
                EstadoOperacionalFolio::Disponible->value,
            ],
        ];
    }

    private function serializar''',
)
regex(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    r"    private function serializar\(FolioMaterial \$material\): array\n    \{.*?\n        return \[\n            'folio_id' => \$folio->id,",
    '''    private function serializar(FolioMaterial $material): array
    {
        $folio = $material->folio;
        $ubicacion = $folio->ubicacionActual;
        $posicion = $ubicacion?->posicion;
        $camara = $ubicacion?->camara ?? $posicion?->camara;
        $enCamara = $camara?->contenido === ContenidoCamara::Materiales;
        $reservable = $enCamara
            && $camara->estado === EstadoCamara::Activa
            && (! $posicion || $posicion->estado === EstadoPosicion::Activa)
            && $folio->estado_operacional === EstadoOperacionalFolio::Disponible
            && $material->motivo_bloqueo === null;
        $disponible = $reservable
            ? max(0, (float) $material->cantidad_actual - (float) $material->cantidad_reservada)
            : 0;
        $recepcion = $material->bultoRecepcion?->detalle?->recepcion;

        return [
            'folio_id' => $folio->id,''',
)
replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    "            'estado_ubicacion' => $ubicado ? 'ubicado' : 'pendiente_ubicacion',\n",
    "            'estado_ubicacion' => ! $enCamara\n                ? 'pendiente_ubicacion'\n                : ($posicion ? 'ubicado' : 'solo_camara'),\n",
)
replace(
    'app/Services/Materiales/ServicioConsultaInventarioMaterial.php',
    """            'camara' => $posicion?->camara ? [
                'id' => $posicion->camara->id,
                'codigo' => $posicion->camara->codigo,
                'nombre' => $posicion->camara->nombre,
            ] : null,
""",
    """            'camara' => $camara ? [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
            ] : null,
""",
)

replace(
    'app/Services/Existencias/ServicioExistencias.php',
    "                'folio.ubicacionActual.posicion.camara',\n",
    "                'folio.ubicacionActual.camara',\n                'folio.ubicacionActual.posicion',\n",
)
replace(
    'app/Services/Existencias/ServicioExistencias.php',
    """                $posicion = $folio->ubicacionActual?->posicion;
                $camara = $posicion?->camara;
                $ubicado = $camara?->contenido === ContenidoCamara::Materiales;
                $reservable = $ubicado
                    && $posicion?->estado === EstadoPosicion::Activa
                    && $camara?->estado === EstadoCamara::Activa
""",
    """                $ubicacion = $folio->ubicacionActual;
                $posicion = $ubicacion?->posicion;
                $camara = $ubicacion?->camara ?? $posicion?->camara;
                $ubicado = $camara?->contenido === ContenidoCamara::Materiales;
                $reservable = $ubicado
                    && (! $posicion || $posicion->estado === EstadoPosicion::Activa)
                    && $camara?->estado === EstadoCamara::Activa
""",
)
replace(
    'app/Services/Existencias/ServicioExistencias.php',
    "                    'estado_ubicacion' => $ubicado ? 'Ubicado' : 'Pendiente de ubicación',\n",
    "                    'estado_ubicacion' => ! $ubicado\n                        ? 'Pendiente de ubicación'\n                        : ($posicion ? 'Ubicación exacta' : 'Solo en cámara'),\n",
)

replace(
    'app/Services/Materiales/ServicioDespachoMaterial.php',
    'use App\\Enums\\ContenidoCamara;\n',
    'use App\\Enums\\ContenidoCamara;\nuse App\\Enums\\EstadoCamara;\nuse App\\Enums\\EstadoPosicion;\n',
)
text = read('app/Services/Materiales/ServicioDespachoMaterial.php')
text = text.replace("'folio.ubicacionActual.posicion.camara'", "'folio.ubicacionActual.camara', 'folio.ubicacionActual.posicion'")
write('app/Services/Materiales/ServicioDespachoMaterial.php', text)
replace(
    'app/Services/Materiales/ServicioDespachoMaterial.php',
    """                $ubicacion = $folioMaterial->folio->ubicacionActual;
                $posicion = $ubicacion?->posicion;
                $camara = $posicion?->camara;

                if (! $ubicacion || ! $posicion || ! $camara
                    || $camara->contenido !== ContenidoCamara::Materiales) {
                    throw new DomainException('El folio no posee una ubicación material válida.');
                }
""",
    """                $ubicacion = $folioMaterial->folio->ubicacionActual;
                $posicion = $ubicacion?->posicion;
                $camara = $ubicacion?->camara ?? $posicion?->camara;

                if (! $ubicacion || ! $camara
                    || $camara->contenido !== ContenidoCamara::Materiales
                    || $camara->estado !== EstadoCamara::Activa
                    || ($posicion && $posicion->estado !== EstadoPosicion::Activa)) {
                    throw new DomainException('El folio no posee una ubicación material válida.');
                }
""",
)
text = read('app/Services/Materiales/ServicioDespachoMaterial.php')
text = text.replace("'posicion_id' => $posicion->id", "'posicion_id' => $posicion?->id")
text = text.replace("'posicion' => $posicion->etiqueta", "'posicion' => $posicion?->etiqueta")
write('app/Services/Materiales/ServicioDespachoMaterial.php', text)

replace(
    'app/Http/Resources/DespachoMaterialResource.php',
    """                                $posicion = $folio->ubicacionActual?->posicion;

                                return [
""",
    """                                $ubicacion = $folio->ubicacionActual;
                                $posicion = $ubicacion?->posicion;
                                $camara = $ubicacion?->camara ?? $posicion?->camara;

                                return [
""",
)
replace(
    'app/Http/Resources/DespachoMaterialResource.php',
    """                                    'camara' => $posicion?->camara ? [
                                        'id' => $posicion->camara->id,
                                        'codigo' => $posicion->camara->codigo,
                                        'nombre' => $posicion->camara->nombre,
                                    ] : null,
""",
    """                                    'camara' => $camara ? [
                                        'id' => $camara->id,
                                        'codigo' => $camara->codigo,
                                        'nombre' => $camara->nombre,
                                    ] : null,
""",
)

text = read('app/Services/Materiales/ServicioTransformacionMaterial.php')
text = text.replace("'folio.ubicacionActual.posicion.camara'", "'folio.ubicacionActual.camara', 'folio.ubicacionActual.posicion'")
text = text.replace("$camara = $ubicacion?->posicion?->camara;", "$camara = $ubicacion?->camara ?? $ubicacion?->posicion?->camara;")
text = text.replace(
    "$metadatosUbicacion = $ubicacion?->posicion ? [\n                    'camara' => $ubicacion->posicion->camara->codigo,\n                    'posicion' => $ubicacion->posicion->etiqueta,\n                ] : [];",
    "$metadatosUbicacion = $camara ? [\n                    'camara' => $camara->codigo,\n                    'posicion' => $ubicacion?->posicion?->etiqueta,\n                ] : [];",
)
write('app/Services/Materiales/ServicioTransformacionMaterial.php', text)

print('Bloque 2 aplicado')
