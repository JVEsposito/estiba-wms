from pathlib import Path
import re


def replace(path: str, old: str, new: str, count: int = 1) -> None:
    file = Path(path)
    text = file.read_text()
    found = text.count(old)
    if found < count:
        raise RuntimeError(f'{path}: expected {count} occurrence(s), found {found}: {old[:160]!r}')
    file.write_text(text.replace(old, new, count))


# 1. Despacho: excluir bloqueados al reservar y volver a validar antes de retirar.
replace(
    'app/Services/Materiales/ServicioDespachoMaterial.php',
    "            ->where('folios_materiales.item_material_id', $detalle->item_material_id)\n            ->where('folios.activo', true)\n            ->whereHas('folio.ubicacionActual.posicion.camara', fn ($consulta) => $consulta",
    "            ->where('folios_materiales.item_material_id', $detalle->item_material_id)\n            ->whereNull('folios_materiales.motivo_bloqueo')\n            ->where('folios.activo', true)\n            ->where('folios.estado_operacional', EstadoOperacionalFolio::Disponible->value)\n            ->whereHas('folio.ubicacionActual.posicion.camara', fn ($consulta) => $consulta",
)
replace(
    'app/Services/Materiales/ServicioDespachoMaterial.php',
    "                $folioMaterial = FolioMaterial::query()\n                    ->with(['folio.ubicacionActual.posicion.camara', 'item'])\n                    ->lockForUpdate()\n                    ->findOrFail($datosRetiro['folio_id']);\n                $detalle = $despacho->detalles->firstWhere(",
    "                $folioMaterial = FolioMaterial::query()\n                    ->with(['folio.ubicacionActual.posicion.camara', 'item'])\n                    ->lockForUpdate()\n                    ->findOrFail($datosRetiro['folio_id']);\n\n                if (! $folioMaterial->folio?->activo\n                    || $folioMaterial->motivo_bloqueo !== null\n                    || $folioMaterial->folio->estado_operacional !== EstadoOperacionalFolio::Disponible) {\n                    throw new DomainException(\n                        'El folio se encuentra bloqueado o no está disponible para retiro.',\n                    );\n                }\n\n                $detalle = $despacho->detalles->firstWhere(",
)

# 2. Ubicación inicial: un folio de Materiales debe existir antes de estibarse.
for import_line in [
    'use App\\Enums\\TipoMovimientoInventarioMaterial;\n',
    'use App\\Models\\ItemMaterial;\n',
    'use App\\Models\\MovimientoInventarioMaterial;\n',
]:
    replace('app/Services/Estiba/ServicioMovimientoEstiba.php', import_line, '')
replace(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    '     * Ubica un folio por primera vez y lo crea si todavía no existe.',
    '     * Ubica un folio por primera vez. Solo los folios de productos pueden nacer aquí; los de Materiales deben existir previamente.',
)
replace(
    'app/Services/Estiba/ServicioMovimientoEstiba.php',
    "        if (! $folio) {\n            $folio = Folio::create($this->atributosNuevoFolio(\n                $numeroFolio,\n                $tipoBulto,\n                $generadoDispositivoAt,\n                $datosFolio,\n            ));\n\n            if ($tipoBulto === TipoBulto::Material) {\n                $this->crearFichaMaterial(\n                    $folio,\n                    $datosMaterial,\n                    $usuario,\n                    $dispositivo,\n                    $recibidoServidorAt,\n                );\n            }\n        } elseif ($folio->tipo_bulto !== $tipoBulto) {",
    "        if (! $folio) {\n            if ($tipoBulto === TipoBulto::Material) {\n                throw new DomainException(\n                    'El folio de material no existe. Debe nacer desde Recepción, Transformación o una migración controlada antes de ubicarlo.',\n                );\n            }\n\n            $folio = Folio::create($this->atributosNuevoFolio(\n                $numeroFolio,\n                $tipoBulto,\n                $generadoDispositivoAt,\n                $datosFolio,\n            ));\n        } elseif ($folio->tipo_bulto !== $tipoBulto) {",
)
movement_path = Path('app/Services/Estiba/ServicioMovimientoEstiba.php')
movement_text = movement_path.read_text()
pattern = re.compile(
    r"\n    /\*\*\n     \* @param  array<string, mixed>  \$datos\n     \*/\n    private function crearFichaMaterial\(.*?\n    }\n\n    private function validarNumeroFolio",
    re.S,
)
movement_text, substitutions = pattern.subn('\n\n    private function validarNumeroFolio', movement_text, count=1)
if substitutions != 1:
    raise RuntimeError(f'No se pudo retirar crearFichaMaterial; reemplazos={substitutions}')
movement_path.write_text(movement_text)

# 3. Corrección de ítem: proteger reservas y genealogía de Transformación.
replace(
    'app/Services/Materiales/ServicioCorreccionItemMaterial.php',
    'use App\\Models\\CorreccionItemFolioMaterial;\n',
    'use App\\Models\\ConsumoTransformacionMaterial;\nuse App\\Models\\CorreccionItemFolioMaterial;\n',
)
replace(
    'app/Services/Materiales/ServicioCorreccionItemMaterial.php',
    "            if ($material->reservas()\n                ->where('estado', EstadoReservaMaterial::Activa->value)\n                ->lockForUpdate()\n                ->exists()) {\n                throw new DomainException('El ítem posee reservas activas y no puede corregirse.');\n            }\n\n            if ($material->retiros()->lockForUpdate()->exists()) {",
    "            if ((float) $material->cantidad_reservada > 0) {\n                throw new DomainException(\n                    'El folio posee cantidad reservada y no puede corregirse.',\n                );\n            }\n\n            if ($material->reservas()\n                ->where('estado', EstadoReservaMaterial::Activa->value)\n                ->lockForUpdate()\n                ->exists()) {\n                throw new DomainException('El ítem posee reservas activas de despacho y no puede corregirse.');\n            }\n\n            if ($material->reservasTransformacion()\n                ->where('estado', EstadoReservaMaterial::Activa->value)\n                ->lockForUpdate()\n                ->exists()) {\n                throw new DomainException(\n                    'El ítem posee reservas activas de transformación y no puede corregirse.',\n                );\n            }\n\n            if (ConsumoTransformacionMaterial::query()\n                ->where('folio_id', $material->folio_id)\n                ->lockForUpdate()\n                ->exists()) {\n                throw new DomainException(\n                    'El folio ya registra consumos de transformación y no puede corregirse.',\n                );\n            }\n\n            if ($material->lote_transformacion_origen_id !== null) {\n                throw new DomainException(\n                    'Un producto generado por transformación no puede cambiar de ítem.',\n                );\n            }\n\n            if ($material->retiros()->lockForUpdate()->exists()) {",
)

# 4. Pruebas de Materiales: los fixtures crean el folio antes de ubicarlo.
replace(
    'tests/Feature/Api/MaterialesApiTest.php',
    'use App\\Models\\Dispositivo;\nuse App\\Models\\FolioMaterial;',
    'use App\\Models\\Dispositivo;\nuse App\\Models\\Folio;\nuse App\\Models\\FolioMaterial;',
)
materiales_path = Path('tests/Feature/Api/MaterialesApiTest.php')
materiales_text = materiales_path.read_text()
old_test_pattern = re.compile(
    r"    public function test_ubica_material_solo_en_su_tipo_de_camara_y_crea_kardex_de_ingreso\(\): void\n    \{.*?\n    \}\n\n    public function test_reserva_fifo_y_permite_retiros_parciales_hasta_liberar_el_folio",
    re.S,
)
new_test = r'''    public function test_ubicacion_material_exige_folio_preexistente_y_no_permite_altas_manuales(): void
    {
        [$administrador] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        [, , $tokenFrio] = $this->crearCamareroFrio();
        $item = $this->crearItem($administrador);
        [$camaraMaterial, $posicionMaterial] = $this->crearCamara('CAM-01', ContenidoCamara::Materiales);
        [$camaraProducto, $posicionProducto] = $this->crearCamara('CAM-02', ContenidoCamara::Productos);
        $sesionMaterial = $this->abrirSesion($tokenTablet, $camaraMaterial);
        $sesionProducto = $this->abrirSesion($tokenFrio, $camaraProducto);

        $this->conToken($tokenFrio)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicionProducto,
                $sesionProducto,
                $item,
                'MAT-RECHAZADO',
                0,
                25,
            ))
            ->assertUnprocessable();

        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicionMaterial,
                $sesionMaterial,
                $item,
                'MAT-0001',
                0,
                25.5,
            ))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'El folio de material no existe. Debe nacer desde Recepción, Transformación o una migración controlada antes de ubicarlo.',
            );

        $this->assertDatabaseMissing('folios', ['numero_folio' => 'MAT-RECHAZADO']);
        $this->assertDatabaseMissing('folios', ['numero_folio' => 'MAT-0001']);

        $folioId = $this->crearFolioMaterialPendiente(
            $item,
            'FGE0000001',
            25.5,
            now()->toAtomString(),
        );
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicionMaterial,
                $sesionMaterial,
                $item,
                'FGE0000001',
                0,
                25.5,
            ))
            ->assertOk()
            ->assertJsonPath('data.folio.id', $folioId)
            ->assertJsonPath('data.folio.tipo_bulto', 'material');

        $this->assertDatabaseHas('ubicaciones_actuales', [
            'folio_id' => $folioId,
            'posicion_id' => $posicionMaterial->id,
        ]);
        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folioId,
            'item_material_id' => $item->id,
            'cantidad_actual' => 25.500,
        ]);
        $this->assertDatabaseHas('movimientos_inventario_materiales', [
            'folio_id' => $folioId,
            'tipo' => 'ingreso_recepcion',
            'cantidad' => 25.500,
        ]);
    }

    public function test_reserva_fifo_y_permite_retiros_parciales_hasta_liberar_el_folio'''
materiales_text, replacements = old_test_pattern.subn(new_test, materiales_text, count=1)
if replacements != 1:
    raise RuntimeError(f'No se pudo reemplazar prueba de alta manual; reemplazos={replacements}')

# Precrear el folio del otro cliente para que el conflicto siga evaluando la mezcla de clientes.
old_other_client = """        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $itemOtroCliente,
                'BULTO-OTRO-CLIENTE',
                2,
                2,
            ))"""
new_other_client = """        $this->crearFolioMaterialPendiente(
            $itemOtroCliente,
            'BULTO-OTRO-CLIENTE',
            2,
            now()->toAtomString(),
        );
        $this->conToken($tokenTablet)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $itemOtroCliente,
                'BULTO-OTRO-CLIENTE',
                2,
                2,
            ))"""
if old_other_client not in materiales_text:
    raise RuntimeError('No se encontró escenario de otro cliente')
materiales_text = materiales_text.replace(old_other_client, new_other_client, 1)

# Agregar regresiones de reserva y retiro de bloqueados antes de la prueba de inventario.
anchor = '    public function test_inventario_refleja_el_stock_disponible_despues_de_reservar(): void\n'
if anchor not in materiales_text:
    raise RuntimeError('No se encontró ancla para pruebas de bloqueo en despacho')
blocked_tests = r'''    public function test_despacho_excluye_folios_bloqueados_de_la_reserva_fifo(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicionUno, $posicionDos] = $this->crearCamara(
            'MAT-BLOQ-DES',
            ContenidoCamara::Materiales,
            2,
        );
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioBloqueado = $this->ubicarMaterial(
            $tokenTablet,
            $posicionUno,
            $sesion,
            $item,
            'FGE1000001',
            0,
            10,
            now()->subDay()->toAtomString(),
        );
        $folioDisponible = $this->ubicarMaterial(
            $tokenTablet,
            $posicionDos,
            $sesion,
            $item,
            'FGE1000002',
            1,
            10,
            now()->toAtomString(),
        );
        FolioMaterial::findOrFail($folioBloqueado)->update([
            'motivo_bloqueo' => 'Material retenido por calidad.',
        ]);
        Folio::findOrFail($folioBloqueado)->update([
            'estado_operacional' => 'bloqueado',
        ]);

        $this->conToken($tokenOficina)
            ->postJson('/api/materiales/despachos', [
                'operacion_id' => (string) Str::uuid(),
                'destino_material_id' => $destino->id,
                'items' => [[
                    'item_material_id' => $item->id,
                    'cantidad' => 5,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.sugerencias_fifo.0.folio_id', $folioDisponible);

        $this->assertDatabaseMissing('reservas_materiales', [
            'folio_id' => $folioBloqueado,
            'estado' => 'activa',
        ]);
        $this->assertSame('0.000', FolioMaterial::findOrFail($folioBloqueado)->cantidad_reservada);
        $this->assertSame('5.000', FolioMaterial::findOrFail($folioDisponible)->cantidad_reservada);
    }

    public function test_retiro_revalida_el_bloqueo_dentro_de_la_transaccion(): void
    {
        [$administrador, $tokenOficina] = $this->crearAdministrador();
        [, , $tokenTablet] = $this->crearOperador();
        $item = $this->crearItem($administrador);
        $destino = $this->crearDestino($administrador);
        [$camara, $posicion] = $this->crearCamara('MAT-RETIRO-BLOQ', ContenidoCamara::Materiales);
        $sesion = $this->abrirSesion($tokenTablet, $camara);
        $folioId = $this->ubicarMaterial(
            $tokenTablet,
            $posicion,
            $sesion,
            $item,
            'FGE2000001',
            0,
            10,
            now()->toAtomString(),
        );
        $despachoId = $this->crearDespacho($tokenOficina, $item, $destino, 5);
        $this->assertSame('5.000', FolioMaterial::findOrFail($folioId)->cantidad_reservada);

        // Simula un estado legado o concurrente que cambió después de reservar.
        FolioMaterial::findOrFail($folioId)->update([
            'motivo_bloqueo' => 'Bloqueo aplicado después de la reserva.',
        ]);
        Folio::findOrFail($folioId)->update([
            'estado_operacional' => 'bloqueado',
        ]);

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/despachos/{$despachoId}/retirar", [
                'operacion_id' => (string) Str::uuid(),
                'retiros' => [[
                    'folio_id' => $folioId,
                    'cantidad' => 1,
                    'sesion_estiba_id' => $sesion,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'El folio se encuentra bloqueado o no está disponible para retiro.',
            );

        $material = FolioMaterial::findOrFail($folioId);
        $this->assertSame('10.000', $material->cantidad_actual);
        $this->assertSame('5.000', $material->cantidad_reservada);
        $this->assertDatabaseCount('retiros_materiales', 0);
    }

'''
materiales_text = materiales_text.replace(anchor, blocked_tests + anchor, 1)

# Cambiar el helper: primero crea el folio y su existencia, después solicita solo la ubicación física.
old_helper = r'''    private function ubicarMaterial(
        string $token,
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        string $numeroFolio,
        int $version,
        float $cantidad,
        string $fecha,
    ): string {
        return $this->conToken($token)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $item,
                $numeroFolio,
                $version,
                $cantidad,
                $fecha,
            ))
            ->assertOk()
            ->json('data.folio.id');
    }

'''
new_helper = r'''    private function ubicarMaterial(
        string $token,
        Posicion $posicion,
        string $sesion,
        ItemMaterial $item,
        string $numeroFolio,
        int $version,
        float $cantidad,
        string $fecha,
    ): string {
        $this->crearFolioMaterialPendiente($item, $numeroFolio, $cantidad, $fecha);

        return $this->conToken($token)
            ->postJson('/api/movimientos/ubicar', $this->payloadUbicacion(
                $posicion,
                $sesion,
                $item,
                $numeroFolio,
                $version,
                $cantidad,
                $fecha,
            ))
            ->assertOk()
            ->json('data.folio.id');
    }

    private function crearFolioMaterialPendiente(
        ItemMaterial $item,
        string $numeroFolio,
        float $cantidad,
        string $fecha,
    ): string {
        $existente = Folio::query()->where('numero_folio', $numeroFolio)->first();

        if ($existente) {
            return $existente->id;
        }

        $item->loadMissing('cliente.temporada');
        $folio = Folio::create([
            'temporada_id' => $item->cliente?->temporada?->temporada_id,
            'numero_folio' => $numeroFolio,
            'tipo_bulto' => 'material',
            'estado_operacional' => 'pendiente_ubicacion',
            'fecha_ingreso' => $fecha,
            'activo' => true,
            'origen_sistema' => 'recepcion_materiales',
            'estado_integracion' => 'no_vinculado',
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'categoria_operacional' => $item->categoria_operacional?->value,
            'cantidad_inicial' => $cantidad,
            'cantidad_actual' => $cantidad,
            'cantidad_reservada' => 0,
            'unidad_medida' => $item->unidad_medida,
            'lote' => 'L-2026-07',
            'proveedor' => 'Proveedor de prueba',
        ]);
        MovimientoInventarioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'tipo' => 'ingreso_recepcion',
            'cantidad' => $cantidad,
            'cantidad_anterior' => 0,
            'cantidad_resultante' => $cantidad,
            'user_id' => $item->creado_por_user_id,
            'motivo' => 'Nacimiento controlado del folio de prueba.',
            'ocurrido_at' => $fecha,
        ]);

        return $folio->id;
    }

'''
if old_helper not in materiales_text:
    raise RuntimeError('No se encontró helper ubicarMaterial')
materiales_text = materiales_text.replace(old_helper, new_helper, 1)
materiales_path.write_text(materiales_text)

# 5. Fixture de Bloqueo: crear el folio antes de invocar la ubicación.
bloqueo_path = Path('tests/Feature/Api/BloqueoMaterialApiTest.php')
bloqueo_text = bloqueo_path.read_text()
old_bloqueo = r'''        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: 'FGE1234567',
            tipoBulto: TipoBulto::Material,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
            datosMaterial: [
                'item_material_id' => $item->id,
                'cantidad' => 10,
            ],
        );
'''
new_bloqueo = r'''        $folio = Folio::create([
            'numero_folio' => 'FGE1234567',
            'tipo_bulto' => TipoBulto::Material,
            'estado_operacional' => EstadoOperacionalFolio::PendienteUbicacion,
            'fecha_ingreso' => now(),
            'activo' => true,
            'origen_sistema' => 'recepcion_materiales',
        ]);
        FolioMaterial::create([
            'folio_id' => $folio->id,
            'item_material_id' => $item->id,
            'cantidad_inicial' => 10,
            'cantidad_actual' => 10,
            'cantidad_reservada' => 0,
            'unidad_medida' => $item->unidad_medida,
        ]);
        $movimiento = app(ServicioMovimientoEstiba::class)->ubicar(
            operacionId: (string) Str::uuid(),
            numeroFolio: $folio->numero_folio,
            tipoBulto: TipoBulto::Material,
            posicionDestino: $posicion,
            sesionDestino: $sesion,
            usuario: $operador,
            dispositivo: $dispositivo,
            versionDestinoConocida: 0,
            generadoDispositivoAt: now(),
        );
'''
if old_bloqueo not in bloqueo_text:
    raise RuntimeError('No se encontró fixture de bloqueo')
bloqueo_path.write_text(bloqueo_text.replace(old_bloqueo, new_bloqueo, 1))

# 6. Transformación: una reserva activa impide cambiar el ítem del folio.
transform_path = Path('tests/Feature/Api/TransformacionMaterialApiTest.php')
transform_text = transform_path.read_text()
anchor_transform = """        $this->assertSame($administrador->id, $planificada['creado_por']['id']);

        $operacionCancelacion = (string) Str::uuid();"""
insert_transform = """        $this->assertSame($administrador->id, $planificada['creado_por']['id']);

        $itemAlternativo = $this->crearItem(
            ClienteMaterial::findOrFail($entradaPrincipal->cliente_material_id),
            $administrador,
            'CAJA-DES-CORR',
            'Caja desarmada alternativa',
            CategoriaOperacionalMaterial::MaterialMp,
        );
        $folioPrincipalId = Folio::query()
            ->where('numero_folio', $folioPrincipal)
            ->value('id');
        $this->conToken($tokenOficina)
            ->postJson("/api/materiales/inventario/{$folioPrincipalId}/corregir-item", [
                'operacion_id' => (string) Str::uuid(),
                'item_material_id' => $itemAlternativo->id,
                'motivo' => 'Intento de corrección con reserva de transformación activa.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->assertDatabaseHas('folios_materiales', [
            'folio_id' => $folioPrincipalId,
            'item_material_id' => $entradaPrincipal->id,
        ]);

        $operacionCancelacion = (string) Str::uuid();"""
if anchor_transform not in transform_text:
    raise RuntimeError('No se encontró ancla de reserva de transformación')
transform_path.write_text(transform_text.replace(anchor_transform, insert_transform, 1))

# 7. Documentación de invariantes.
doc_path = Path('docs/reglas-negocio.md')
doc = doc_path.read_text()
doc += r'''

## Invariantes físicos de Materiales

- Un folio de Materiales no puede nacer desde la operación genérica de ubicación. Debe existir previamente por Recepción, Transformación o migración controlada.
- La ubicación inicial de Materiales solo asigna una posición física a un folio existente con ficha de inventario válida.
- Un folio bloqueado o con estado distinto de `disponible` no participa en reservas FIFO de despacho.
- El retiro vuelve a validar, dentro de la transacción y con bloqueo pesimista, que el folio siga activo, disponible y sin motivo de bloqueo.
- La corrección supervisada de ítem se rechaza cuando existe cantidad reservada, reservas activas de despacho o transformación, consumos de transformación, origen como producto transformado o retiros previos.
- Las validaciones de interfaz son auxiliares; estas reglas se aplican obligatoriamente en backend.
'''
doc_path.write_text(doc)
