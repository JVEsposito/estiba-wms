from pathlib import Path
import re


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    content = file.read_text(encoding="utf-8")
    if content.count(old) != 1:
        raise RuntimeError(f"Se esperaba una coincidencia en {path}, encontradas: {content.count(old)}")
    file.write_text(content.replace(old, new, 1), encoding="utf-8")


navigation_path = "resources/views/components/office/navigation.blade.php"
replace_once(
    navigation_path,
    "            ['key' => 'inventario', 'module' => 'materiales.inventario', 'label' => 'Inventario', 'href' => '/oficina/materiales/inventario', 'permissions' => ['puede_consultar_despachos_materiales']],\n            ['key' => 'despachos', 'module' => 'materiales.despachos', 'label' => 'Despachos', 'href' => '/oficina/materiales/despachos', 'permissions' => ['puede_consultar_despachos_materiales']],",
    "            ['key' => 'inventario', 'module' => 'materiales.inventario', 'label' => 'Inventario', 'href' => '/oficina/materiales/inventario', 'permissions' => ['puede_consultar_despachos_materiales']],\n            ['key' => 'custodia', 'module' => 'materiales.custodia', 'label' => 'Custodia', 'href' => '/oficina/materiales/almacenes', 'permissions' => ['puede_consultar_despachos_materiales']],\n            ['key' => 'despachos', 'module' => 'materiales.despachos', 'label' => 'Despachos', 'href' => '/oficina/materiales/despachos', 'permissions' => ['puede_consultar_despachos_materiales']],",
)

service_path = Path("app/Services/Existencias/ServicioExistencias.php")
service = service_path.read_text(encoding="utf-8")
service = service.replace("use App\\Models\\FolioMaterial;", "use App\\Models\\SaldoMaterialAlmacen;", 1)
service = service.replace(
    "                'descripcion' => 'Folios de materiales con cantidad actual, reservada y disponible por unidad.',",
    "                'descripcion' => 'Saldos de materiales distribuidos entre Bodega y centros de costo, con total empresa.',",
    1,
)
service = service.replace(
    "                    ['clave' => 'lote', 'titulo' => 'Lote', 'ancho' => 18],\n                    ['clave' => 'cantidad_inicial', 'titulo' => 'Cantidad inicial', 'ancho' => 17, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_actual', 'titulo' => 'Cantidad actual', 'ancho' => 17, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_reservada', 'titulo' => 'Cantidad reservada', 'ancho' => 19, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_disponible', 'titulo' => 'Cantidad disponible', 'ancho' => 20, 'tipo' => 'numero'],",
    "                    ['clave' => 'lote', 'titulo' => 'Lote', 'ancho' => 18],\n                    ['clave' => 'tipo_almacen', 'titulo' => 'Tipo de almacén', 'ancho' => 18],\n                    ['clave' => 'codigo_almacen', 'titulo' => 'Código de almacén', 'ancho' => 20],\n                    ['clave' => 'almacen', 'titulo' => 'Almacén / centro de costo', 'ancho' => 30],\n                    ['clave' => 'centro_costo', 'titulo' => 'Centro de costo', 'ancho' => 20],\n                    ['clave' => 'cantidad_inicial', 'titulo' => 'Cantidad inicial del folio', 'ancho' => 21, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_actual', 'titulo' => 'Cantidad actual en almacén', 'ancho' => 24, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_reservada', 'titulo' => 'Cantidad reservada en almacén', 'ancho' => 27, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_disponible', 'titulo' => 'Cantidad disponible en almacén', 'ancho' => 28, 'tipo' => 'numero'],\n                    ['clave' => 'cantidad_total_empresa', 'titulo' => 'Cantidad total empresa', 'ancho' => 22, 'tipo' => 'numero'],",
    1,
)

new_materials_method = '''    /** @return LazyCollection<int, array<string, mixed>> */
    private function materiales(): LazyCollection
    {
        return SaldoMaterialAlmacen::query()
            ->with([
                'folioMaterial.folio',
                'folioMaterial.item.cliente.temporada',
                'folioMaterial.item.cliente.cliente',
                'folioMaterial.proveedorMaterial',
                'almacen',
                'camara',
                'posicion',
            ])
            ->where('cantidad_actual', '>', 0)
            ->whereHas('folioMaterial.folio', fn ($consulta) => $consulta->where('activo', true))
            ->whereHas(
                'folioMaterial.item.cliente.temporada',
                fn ($consulta) => $consulta->where('activa', true),
            )
            ->orderBy('almacen_material_id')
            ->orderBy('folio_id')
            ->lazy(200)
            ->map(function (SaldoMaterialAlmacen $saldo): array {
                $material = $saldo->folioMaterial;
                $folio = $material->folio;
                $almacen = $saldo->almacen;
                $posicion = $saldo->posicion;
                $camara = $saldo->camara ?? $posicion?->camara;
                $almacenFisico = $almacen?->requiere_ubicacion_fisica === true;
                $ubicacionValida = ! $almacenFisico
                    || ($camara?->contenido === ContenidoCamara::Materiales
                        && (! $posicion || $posicion->estado === EstadoPosicion::Activa)
                        && $camara->estado === EstadoCamara::Activa);
                $reservable = $ubicacionValida
                    && $folio->estado_operacional === EstadoOperacionalFolio::Disponible
                    && $material->motivo_bloqueo === null;
                $actual = (float) $saldo->cantidad_actual;
                $reservada = (float) $saldo->cantidad_reservada;
                $disponible = $reservable ? max(0, $actual - $reservada) : 0;

                return [
                    'temporada' => $material->item->cliente->temporada?->codigo,
                    'folio' => $folio->numero_folio,
                    'codigo_item' => $material->item->codigo,
                    'item' => $material->item->nombre,
                    'categoria_operacional' => $this->humanizar($material->categoria_operacional?->value),
                    'cliente' => $material->item->cliente->nombre,
                    'proveedor' => $material->proveedor ?? $material->proveedorMaterial?->nombre,
                    'lote' => $material->lote,
                    'tipo_almacen' => $almacen?->tipo
                        ? $this->humanizar($almacen->tipo->value)
                        : null,
                    'codigo_almacen' => $almacen?->codigo,
                    'almacen' => $almacen?->nombre,
                    'centro_costo' => $almacen?->centro_costo,
                    'cantidad_inicial' => (float) $material->cantidad_inicial,
                    'cantidad_actual' => $actual,
                    'cantidad_reservada' => $reservada,
                    'cantidad_disponible' => $disponible,
                    'cantidad_total_empresa' => (float) $material->cantidad_actual,
                    'unidad_medida' => $material->unidad_medida,
                    'estado_operacional' => $this->humanizar($folio->estado_operacional->value),
                    'estado_ubicacion' => ! $almacenFisico
                        ? 'Almacén virtual'
                        : (! $camara
                            ? 'Pendiente de ubicación'
                            : ($posicion ? 'Ubicado' : 'Solo en cámara')),
                    'reservable' => $reservable ? 'Sí' : 'No',
                    'motivo_bloqueo' => $material->motivo_bloqueo,
                    'camara' => $camara ? trim($camara->codigo.' · '.$camara->nombre) : null,
                    'posicion' => $posicion?->etiqueta,
                    'fecha_ingreso' => $folio->fecha_ingreso?->toAtomString(),
                    'fecha_fabricacion' => $material->fecha_fabricacion?->toDateString(),
                    'fecha_vencimiento' => $material->fecha_vencimiento?->toDateString(),
                ];
            });
    }
'''
pattern = re.compile(
    r"    /\*\* @return LazyCollection<int, array<string, mixed>> \*/\n"
    r"    private function materiales\(\): LazyCollection\n"
    r"    \{.*?\n    \}\n\n"
    r"    /\*\* @return LazyCollection<int, array<string, mixed>> \*/\n"
    r"    private function materiaPrima",
    re.S,
)
service, replacements = pattern.subn(
    new_materials_method
    + "\n    /** @return LazyCollection<int, array<string, mixed>> */\n"
    + "    private function materiaPrima",
    service,
    count=1,
)
if replacements != 1:
    raise RuntimeError(f"No fue posible reemplazar materiales(): {replacements}")
service_path.write_text(service, encoding="utf-8")

custody_test = Path("tests/Feature/Api/CustodiaDistribuidaMaterialesTest.php")
test_content = custody_test.read_text(encoding="utf-8")
test_content = test_content.replace(
    "use App\\Models\\User;\n",
    "use App\\Models\\User;\nuse App\\Services\\Existencias\\ServicioExistencias;\n",
    1,
)
needle = """        $this->conToken($tokenOficina)
            ->getJson('/api/materiales/almacenes')
            ->assertOk()
            ->assertJsonPath('perspectivas.bodega', [])
            ->assertJsonPath('perspectivas.centros_costo.0.numero_folio', 'FCU0000001')
            ->assertJsonPath('perspectivas.centros_costo.0.cantidad_actual', '7.000')
            ->assertJsonPath('perspectivas.total_empresa.0.total_empresa', '7.000');

"""
insert = needle + """        $filasExportacion = app(ServicioExistencias::class)
            ->filas(ServicioExistencias::MATERIALES)
            ->filter(fn (array $fila): bool => $fila['folio'] === $folio->numero_folio)
            ->values()
            ->all();

        $this->assertCount(1, $filasExportacion);
        $this->assertSame('Virtual', $filasExportacion[0]['tipo_almacen']);
        $this->assertSame('PACK-01', $filasExportacion[0]['codigo_almacen']);
        $this->assertSame('Packing Línea 1', $filasExportacion[0]['almacen']);
        $this->assertSame('PACK-01', $filasExportacion[0]['centro_costo']);
        $this->assertSame(7.0, $filasExportacion[0]['cantidad_actual']);
        $this->assertSame(7.0, $filasExportacion[0]['cantidad_disponible']);
        $this->assertSame(7.0, $filasExportacion[0]['cantidad_total_empresa']);
        $this->assertSame('Almacén virtual', $filasExportacion[0]['estado_ubicacion']);

"""
if test_content.count(needle) != 1:
    raise RuntimeError("No fue posible insertar la regresión de exportación distribuida")
test_content = test_content.replace(needle, insert, 1)
custody_test.write_text(test_content, encoding="utf-8")

existence_test = Path("tests/Feature/Api/ExistenciasApiTest.php")
existence_content = existence_test.read_text(encoding="utf-8")
existence_content = existence_content.replace(
    "        $this->assertStringContainsString('Cantidad disponible', $contenidoConsulta);",
    "        $this->assertStringContainsString('Cantidad disponible en almacén', $contenidoConsulta);\n"
    "        $this->assertStringContainsString('Centro de costo', $contenidoConsulta);",
    1,
)
existence_content = existence_content.replace(
    """    public function test_oficina_de_existencias_esta_disponible(): void
    {
        $this->get('/oficina/existencias')
            ->assertOk()
            ->assertSee('Tres inventarios. Una fuente oficial.')
            ->assertSee('Excel conectado');
    }
""",
    """    public function test_oficina_de_existencias_esta_disponible(): void
    {
        $this->get('/oficina/existencias')
            ->assertOk()
            ->assertSee('Tres inventarios. Una fuente oficial.')
            ->assertSee('Excel conectado');

        $this->get('/oficina/materiales/almacenes')
            ->assertOk()
            ->assertSee('data-office-key=\"custodia\"', false)
            ->assertSee('Existencia en centros de costo');
    }
""",
    1,
)
existence_test.write_text(existence_content, encoding="utf-8")

print("Custodia visible y exportaciones distribuidas aplicadas.")
