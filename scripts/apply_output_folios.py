from pathlib import Path


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace(path: str, old: str, new: str, expected: int = 1) -> None:
    content = read(path)
    count = content.count(old)
    if count != expected:
        raise RuntimeError(f"{path}: esperaba {expected} coincidencias y encontré {count}\n{old[:180]}")
    write(path, content.replace(old, new))


# Contratos de creación/versionado: opcional para clientes históricos, obligatorio en la UI nueva.
for path in [
    "app/Http/Requests/CrearRecetaMaterialRequest.php",
    "app/Http/Requests/CrearVersionRecetaMaterialRequest.php",
]:
    replace(
        path,
        "            'cantidad_base_salida' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],\n",
        "            'cantidad_base_salida' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],\n"
        "            'unidades_por_folio_salida' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'decimal:0,3'],\n",
    )

# Persistencia de la nueva regla por versión.
replace(
    "app/Models/VersionRecetaMaterial.php",
    "    'cantidad_base_salida',\n    'unidad_medida_salida',\n",
    "    'cantidad_base_salida',\n    'unidades_por_folio_salida',\n    'unidad_medida_salida',\n",
)
replace(
    "app/Models/VersionRecetaMaterial.php",
    "            'cantidad_base_salida' => 'decimal:3',\n            'snapshot' => 'array',\n",
    "            'cantidad_base_salida' => 'decimal:3',\n            'unidades_por_folio_salida' => 'decimal:3',\n            'snapshot' => 'array',\n",
)

# Receta inicial: guarda la capacidad y la congela en el snapshot de la orden.
replace(
    "app/Services/Materiales/ServicioTransformacionMaterial.php",
    "                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),\n                'unidad_medida_salida' => $salida->unidad_medida,\n",
    "                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),\n"
    "                'unidades_por_folio_salida' => isset($datos['unidades_por_folio_salida'])\n"
    "                    ? $this->cantidad($datos['unidades_por_folio_salida'])\n"
    "                    : null,\n"
    "                'unidad_medida_salida' => $salida->unidad_medida,\n",
)
replace(
    "app/Services/Materiales/ServicioTransformacionMaterial.php",
    "                    'cantidad_base' => $version->cantidad_base_salida,\n                    'unidad_medida' => $salida->unidad_medida,\n",
    "                    'cantidad_base' => $version->cantidad_base_salida,\n"
    "                    'unidades_por_folio' => $version->unidades_por_folio_salida,\n"
    "                    'unidad_medida' => $salida->unidad_medida,\n",
)

# Nueva versión de receta: misma regla y snapshot inmutable.
replace(
    "app/Services/Materiales/ServicioVersionRecetaMaterial.php",
    "                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),\n                'unidad_medida_salida' => $receta->itemSalida->unidad_medida,\n",
    "                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),\n"
    "                'unidades_por_folio_salida' => isset($datos['unidades_por_folio_salida'])\n"
    "                    ? $this->cantidad($datos['unidades_por_folio_salida'])\n"
    "                    : null,\n"
    "                'unidad_medida_salida' => $receta->itemSalida->unidad_medida,\n",
)
replace(
    "app/Services/Materiales/ServicioVersionRecetaMaterial.php",
    "                    'cantidad_base' => $version->cantidad_base_salida,\n                    'unidad_medida' => $receta->itemSalida->unidad_medida,\n",
    "                    'cantidad_base' => $version->cantidad_base_salida,\n"
    "                    'unidades_por_folio' => $version->unidades_por_folio_salida,\n"
    "                    'unidad_medida' => $receta->itemSalida->unidad_medida,\n",
)

# API de recetas.
replace(
    "app/Http/Resources/RecetaMaterialResource.php",
    "                    'cantidad_base_salida' => $version->cantidad_base_salida,\n                    'unidad_medida_salida' => $version->unidad_medida_salida,\n",
    "                    'cantidad_base_salida' => $version->cantidad_base_salida,\n"
    "                    'unidades_por_folio_salida' => $version->unidades_por_folio_salida,\n"
    "                    'unidad_medida_salida' => $version->unidad_medida_salida,\n",
)

# API de órdenes: capacidad y avance de folios derivados del snapshot histórico.
replace(
    "app/Http/Resources/OrdenTransformacionMaterialResource.php",
    "    public function toArray(Request $request): array\n    {\n        return [\n",
    "    public function toArray(Request $request): array\n"
    "    {\n"
    "        $unidadesPorFolio = data_get($this->snapshot_receta, 'salida.unidades_por_folio');\n"
    "        $unidadesPorFolio = $unidadesPorFolio !== null\n"
    "            ? round((float) $unidadesPorFolio, 3)\n"
    "            : null;\n"
    "        $foliosPlanificados = $unidadesPorFolio !== null && $unidadesPorFolio > 0\n"
    "            ? (int) ceil((float) $this->cantidad_planificada_salida / $unidadesPorFolio)\n"
    "            : null;\n"
    "        $foliosGenerados = $this->relationLoaded('lotes')\n"
    "            ? $this->lotes->filter(\n"
    "                fn ($lote): bool => $lote->estado?->value === 'cerrado',\n"
    "            )->count()\n"
    "            : null;\n\n"
    "        return [\n",
)
replace(
    "app/Http/Resources/OrdenTransformacionMaterialResource.php",
    "            'cantidad_planificada_salida' => $this->cantidad_planificada_salida,\n            'cantidad_real_salida' => $this->cantidad_real_salida,\n",
    "            'cantidad_planificada_salida' => $this->cantidad_planificada_salida,\n"
    "            'cantidad_real_salida' => $this->cantidad_real_salida,\n"
    "            'unidades_por_folio_salida' => $unidadesPorFolio !== null\n"
    "                ? number_format($unidadesPorFolio, 3, '.', '')\n"
    "                : null,\n"
    "            'folios_planificados' => $foliosPlanificados,\n"
    "            'folios_generados' => $this->when(\n"
    "                $foliosGenerados !== null,\n"
    "                fn (): int => $foliosGenerados,\n"
    "            ),\n"
    "            'folios_pendientes' => $this->when(\n"
    "                $foliosGenerados !== null && $foliosPlanificados !== null,\n"
    "                fn (): int => max(0, $foliosPlanificados - $foliosGenerados),\n"
    "            ),\n",
)

# Backend: una receta configurada abre exactamente el siguiente folio estándar o el remanente final.
replace(
    "app/Services/Materiales/ServicioTransformacionMaterial.php",
    "            $totalPlanificado = round($lotes\n"
    "                ->reject(\n"
    "                    fn (LoteTransformacionMaterial $lote): bool => $lote->estado === EstadoLoteTransformacionMaterial::Anulado,\n"
    "                )\n"
    "                ->sum(fn (LoteTransformacionMaterial $lote): float => (float) $lote->cantidad_planificada_salida)\n"
    "                + $cantidadPlanificada, 3);\n\n"
    "            if ($totalPlanificado - (float) $orden->cantidad_planificada_salida > 0.0001) {\n",
    "            $planificadoAnterior = round($lotes\n"
    "                ->reject(\n"
    "                    fn (LoteTransformacionMaterial $lote): bool => $lote->estado === EstadoLoteTransformacionMaterial::Anulado,\n"
    "                )\n"
    "                ->sum(fn (LoteTransformacionMaterial $lote): float => (float) $lote->cantidad_planificada_salida), 3);\n"
    "            $unidadesPorFolio = data_get($orden->snapshot_receta, 'salida.unidades_por_folio');\n\n"
    "            if ($unidadesPorFolio !== null) {\n"
    "                $unidadesPorFolio = $this->cantidad($unidadesPorFolio);\n"
    "                $restantePlanificado = round(\n"
    "                    (float) $orden->cantidad_planificada_salida - $planificadoAnterior,\n"
    "                    3,\n"
    "                );\n"
    "                $cantidadEsperada = min($unidadesPorFolio, $restantePlanificado);\n\n"
    "                if ($cantidadEsperada <= 0\n"
    "                    || abs($cantidadPlanificada - $cantidadEsperada) > 0.0001) {\n"
    "                    throw new DomainException(sprintf(\n"
    "                        'El siguiente folio debe planificarse con %.3f unidades de salida.',\n"
    "                        max(0, $cantidadEsperada),\n"
    "                    ));\n"
    "                }\n"
    "            }\n\n"
    "            $totalPlanificado = round($planificadoAnterior + $cantidadPlanificada, 3);\n\n"
    "            if ($totalPlanificado - (float) $orden->cantidad_planificada_salida > 0.0001) {\n",
)
replace(
    "app/Services/Materiales/ServicioTransformacionMaterial.php",
    "            if ($orden->estado !== EstadoOrdenTransformacionMaterial::EnProceso\n                || $lote->estado !== EstadoLoteTransformacionMaterial::Abierto) {\n                throw new DomainException('El lote ya no se encuentra abierto para registrar consumos.');\n            }\n\n            $componentes = collect(data_get($orden->snapshot_receta, 'componentes', []));\n",
    "            if ($orden->estado !== EstadoOrdenTransformacionMaterial::EnProceso\n"
    "                || $lote->estado !== EstadoLoteTransformacionMaterial::Abierto) {\n"
    "                throw new DomainException('El lote ya no se encuentra abierto para registrar consumos.');\n"
    "            }\n\n"
    "            $unidadesPorFolio = data_get($orden->snapshot_receta, 'salida.unidades_por_folio');\n\n"
    "            if ($unidadesPorFolio !== null) {\n"
    "                $maximoSalida = min(\n"
    "                    $this->cantidad($unidadesPorFolio),\n"
    "                    (float) $lote->cantidad_planificada_salida,\n"
    "                );\n\n"
    "                if ($cantidadRealSalida - $maximoSalida > 0.0001) {\n"
    "                    throw new DomainException(sprintf(\n"
    "                        'Un folio de salida no puede superar %.3f unidades.',\n"
    "                        $maximoSalida,\n"
    "                    ));\n"
    "                }\n"
    "            }\n\n"
    "            $componentes = collect(data_get($orden->snapshot_receta, 'componentes', []));\n",
)

# Formulario de recetas.
replace(
    "resources/js/office-material-recipes.js",
    "                    <label><span>Cantidad base de salida *</span><input name=\"cantidad_base_salida\" type=\"number\" min=\"0.001\" step=\"0.001\" value=\"1\" required></label>\n",
    "                    <label><span>Cantidad base de salida *</span><input name=\"cantidad_base_salida\" type=\"number\" min=\"0.001\" step=\"0.001\" value=\"1\" required></label>\n"
    "                    <label><span>Unidades por folio / pallet *</span><input name=\"unidades_por_folio_salida\" type=\"number\" min=\"0.001\" step=\"0.001\" value=\"1\" required></label>\n"
    "                    <p class=\"materials-help materials-wide\">Cada lote cerrado genera un folio. Esta cantidad define cuántas unidades contiene normalmente cada pallet o bulto de salida.</p>\n",
)
replace(
    "resources/js/office-material-recipes.js",
    "    recipeElements.form.elements.cantidad_base_salida.value = version.cantidad_base_salida;\n",
    "    recipeElements.form.elements.cantidad_base_salida.value = version.cantidad_base_salida;\n"
    "    recipeElements.form.elements.unidades_por_folio_salida.value = version.unidades_por_folio_salida\n"
    "        || version.cantidad_base_salida;\n",
)
replace(
    "resources/js/office-material-recipes.js",
    "        const amount = Number(data.cantidad_base_salida);\n        if (!Number.isFinite(amount) || amount <= 0) throw new RecipeApiError('La cantidad base de salida debe ser mayor que cero.', 422);\n\n        const payload = {\n            cantidad_base_salida: amount,\n",
    "        const amount = Number(data.cantidad_base_salida);\n"
    "        const unitsPerFolio = Number(data.unidades_por_folio_salida);\n"
    "        if (!Number.isFinite(amount) || amount <= 0) throw new RecipeApiError('La cantidad base de salida debe ser mayor que cero.', 422);\n"
    "        if (!Number.isFinite(unitsPerFolio) || unitsPerFolio <= 0) throw new RecipeApiError('Las unidades por folio deben ser mayores que cero.', 422);\n\n"
    "        const payload = {\n"
    "            cantidad_base_salida: amount,\n"
    "            unidades_por_folio_salida: unitsPerFolio,\n",
)
replace(
    "resources/js/office-material-recipes.js",
    "                    <span>Salida base: ${recipeQuantity(version?.cantidad_base_salida)} ${recipeEscape(version?.unidad_medida_salida || recipe.item_salida?.unidad_medida || '')}</span>\n",
    "                    <span>Salida base: ${recipeQuantity(version?.cantidad_base_salida)} ${recipeEscape(version?.unidad_medida_salida || recipe.item_salida?.unidad_medida || '')}</span>\n"
    "                    <span>Por folio: ${version?.unidades_por_folio_salida ? `${recipeQuantity(version.unidades_por_folio_salida)} ${recipeEscape(version.unidad_medida_salida || recipe.item_salida?.unidad_medida || '')}` : 'Sin regla'}</span>\n",
)

# Oficina de órdenes: cálculo explícito de pallets/folios esperados.
replace(
    "resources/js/office-material-orders.js",
    "    const requirements = requirementsForRecipe(entry, plannedOutput);\n\n    if (!entry) {\n",
    "    const requirements = requirementsForRecipe(entry, plannedOutput);\n"
    "    const unitsPerFolio = Number(entry?.version?.unidades_por_folio_salida || 0);\n"
    "    const expectedFolios = unitsPerFolio > 0 && plannedOutput > 0\n"
    "        ? Math.ceil(plannedOutput / unitsPerFolio)\n"
    "        : 0;\n"
    "    const finalFolioUnits = expectedFolios > 0\n"
    "        ? Math.round((plannedOutput - (expectedFolios - 1) * unitsPerFolio) * 1000) / 1000\n"
    "        : 0;\n\n"
    "    if (!entry) {\n",
)
replace(
    "resources/js/office-material-orders.js",
    "            <div><strong>Salida: ${orderEscape(entry.recipe.item_salida?.codigo)} · ${orderEscape(entry.recipe.item_salida?.nombre)}</strong><small>Receta base ${orderQuantity(entry.version.cantidad_base_salida)} ${orderEscape(entry.version.unidad_medida_salida)}</small></div>\n",
    "            <div><strong>Salida: ${orderEscape(entry.recipe.item_salida?.codigo)} · ${orderEscape(entry.recipe.item_salida?.nombre)}</strong><small>Receta base ${orderQuantity(entry.version.cantidad_base_salida)} ${orderEscape(entry.version.unidad_medida_salida)}${unitsPerFolio > 0 ? ` · ${orderQuantity(unitsPerFolio)} por folio · ${expectedFolios} folios esperados${finalFolioUnits !== unitsPerFolio ? ` (último con ${orderQuantity(finalFolioUnits)})` : ''}` : ' · sin regla de folios'}</small></div>\n",
)

# Contrato móvil.
replace(
    "mobile/src/domain/materialTransformation.ts",
    "    cantidad_base: string;\n    unidad_medida: string;\n",
    "    cantidad_base: string;\n    unidades_por_folio: string | null;\n    unidad_medida: string;\n",
)
replace(
    "mobile/src/domain/materialTransformation.ts",
    "  cantidad_planificada_salida: string;\n  cantidad_real_salida: string | null;\n",
    "  cantidad_planificada_salida: string;\n"
    "  cantidad_real_salida: string | null;\n"
    "  unidades_por_folio_salida: string | null;\n"
    "  folios_planificados: number | null;\n"
    "  folios_generados?: number;\n"
    "  folios_pendientes?: number;\n",
)

# PDA: siguiente pallet sugerido y bloqueado por la regla de la receta.
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "  const closedLots = selected?.lotes.filter((lot) => lot.estado === 'cerrado') ?? [];\n  const lastLot = [...(selected?.lotes ?? [])]\n",
    "  const closedLots = selected?.lotes.filter((lot) => lot.estado === 'cerrado') ?? [];\n"
    "  const unitsPerOutputFolio = Number(\n"
    "    selected?.unidades_por_folio_salida\n"
    "      ?? selected?.receta_snapshot.salida.unidades_por_folio\n"
    "      ?? 0,\n"
    "  );\n"
    "  const plannedInLots = selected?.lotes\n"
    "    .filter((lot) => lot.estado !== 'anulado')\n"
    "    .reduce((sum, lot) => sum + Number(lot.cantidad_planificada_salida), 0) ?? 0;\n"
    "  const remainingPlannedOutput = Math.max(\n"
    "    0,\n"
    "    Number(selected?.cantidad_planificada_salida ?? 0) - plannedInLots,\n"
    "  );\n"
    "  const suggestedLotQuantity = unitsPerOutputFolio > 0\n"
    "    ? Math.min(unitsPerOutputFolio, remainingPlannedOutput)\n"
    "    : 0;\n"
    "  const lastLot = [...(selected?.lotes ?? [])]\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "    setPlannedQuantity('');\n    operationIds.current.clear();\n  }, [selected?.id, openLot?.id]);\n",
    "    setPlannedQuantity(suggestedLotQuantity > 0 ? formatInputQuantity(suggestedLotQuantity) : '');\n"
    "    operationIds.current.clear();\n"
    "  }, [selected?.id, selected?.version, openLot?.id, suggestedLotQuantity]);\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "                <Metric label=\"LOTES CERRADOS\" value={String(closedLots.length)} />\n",
    "                <Metric label=\"FOLIOS GENERADOS\" value={selected.folios_planificados\n"
    "                  ? `${closedLots.length}/${selected.folios_planificados}`\n"
    "                  : String(closedLots.length)} />\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "                  title=\"Abrir lote parcial\"\n                  description=\"Solo puede existir un lote abierto. La suma planificada no puede superar la orden.\"\n",
    "                  title={unitsPerOutputFolio > 0 ? 'Abrir siguiente folio / pallet' : 'Abrir lote parcial'}\n"
    "                  description={unitsPerOutputFolio > 0\n"
    "                    ? `La receta fija ${formatQuantity(unitsPerOutputFolio)} ${selected.receta_snapshot.salida.unidad_medida} por folio. El último puede corresponder al remanente de la orden.`\n"
    "                    : 'Solo puede existir un lote abierto. La suma planificada no puede superar la orden.'}\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "                    keyboardType=\"decimal-pad\"\n                    label=\"Cantidad planificada del lote\"\n                    onChangeText={setPlannedQuantity}\n                    value={plannedQuantity}\n",
    "                    editable={unitsPerOutputFolio <= 0}\n"
    "                    keyboardType=\"decimal-pad\"\n"
    "                    label={unitsPerOutputFolio > 0 ? 'Unidades del siguiente folio' : 'Cantidad planificada del lote'}\n"
    "                    onChangeText={setPlannedQuantity}\n"
    "                    value={plannedQuantity}\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "function Field({\n  keyboardType,\n  label,\n  onChangeText,\n  value,\n}: {\n  keyboardType?: 'default' | 'decimal-pad';\n  label: string;\n  onChangeText: (value: string) => void;\n  value: string;\n}) {\n",
    "function Field({\n"
    "  editable = true,\n"
    "  keyboardType,\n"
    "  label,\n"
    "  onChangeText,\n"
    "  value,\n"
    "}: {\n"
    "  editable?: boolean;\n"
    "  keyboardType?: 'default' | 'decimal-pad';\n"
    "  label: string;\n"
    "  onChangeText: (value: string) => void;\n"
    "  value: string;\n"
    "}) {\n",
)
replace(
    "mobile/src/components/MaterialTransformationOperation.tsx",
    "      <TextInput\n        keyboardType={keyboardType}\n",
    "      <TextInput\n        editable={editable}\n        keyboardType={keyboardType}\n",
)

# Documentación funcional.
replace(
    "docs/MODULO_TRANSFORMACION_MATERIALES.md",
    "- cantidad base de salida;\n- unidad de medida de salida;\n",
    "- cantidad base de salida para el cálculo proporcional;\n"
    "- unidades por folio o pallet de salida;\n"
    "- unidad de medida de salida;\n",
)
replace(
    "docs/MODULO_TRANSFORMACION_MATERIALES.md",
    "Cada lote vincula:\n\n```text\nfolios de entrada\n→ cantidades consumidas\n→ lote de transformación\n→ folios de salida con prefijo por cliente\n```\n",
    "Cada lote vincula:\n\n"
    "```text\nfolios de entrada\n→ cantidades consumidas\n→ lote de transformación\n→ un folio de salida con prefijo por cliente\n```\n\n"
    "Cuando la versión define `unidades_por_folio_salida`, el siguiente lote debe\n"
    "corresponder exactamente a esa capacidad o al remanente final de la orden. Por\n"
    "ejemplo, 12.000 unidades con 120 por folio producen 100 lotes y, al cerrar cada\n"
    "uno, 100 folios. Las versiones históricas sin esta regla conservan sus lotes\n"
    "parciales libres.\n",
)

# La regresión operacional usa los helpers reales del módulo.
test_path = "tests/Feature/Api/TransformacionMaterialApiTest.php"
replace(
    test_path,
    "    private function prepararOrdenOperacional(float $cantidadPlanificada): array\n",
    "    private function prepararOrdenOperacional(\n"
    "        float $cantidadPlanificada,\n"
    "        ?float $unidadesPorFolio = null,\n"
    "    ): array\n",
)
replace(
    test_path,
    "                'nombre' => 'Reversa de consumo agotado',\n                'cantidad_base_salida' => 100,\n                'componentes' => [\n",
    "                'nombre' => 'Reversa de consumo agotado',\n"
    "                'cantidad_base_salida' => 100,\n"
    "                ...($unidadesPorFolio !== null\n"
    "                    ? ['unidades_por_folio_salida' => $unidadesPorFolio]\n"
    "                    : []),\n"
    "                'componentes' => [\n",
)
new_test = r'''
    public function test_configura_un_folio_por_lote_y_calcula_el_remanente_final(): void
    {
        [, $tokenTablet, $folioPrincipal, $folioAuxiliar, $orden] =
            $this->prepararOrdenOperacional(80, 30);

        $this->assertSame('30.000', $orden['unidades_por_folio_salida']);
        $this->assertSame(3, $orden['folios_planificados']);
        $this->assertSame(0, $orden['folios_generados']);
        $this->assertSame(3, $orden['folios_pendientes']);

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 3,
                'cantidad_planificada_salida' => 80,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'El siguiente folio debe planificarse con 30.000 unidades de salida.',
            );

        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 3,
                'cantidad_planificada_salida' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.lotes.0.cantidad_planificada_salida', '30.000')
            ->json('data');
        $loteUno = $orden['lotes'][0];
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteUno['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 4,
                'cantidad_real_salida' => 30,
                'consumos' => [
                    ['folio_id' => $folioPrincipal['id'], 'cantidad' => 30],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 3],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.folios_generados', 1)
            ->assertJsonPath('data.folios_pendientes', 2)
            ->assertJsonCount(1, 'data.lotes.0.salidas')
            ->json('data');

        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 5,
                'cantidad_planificada_salida' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'El siguiente folio debe planificarse con 30.000 unidades de salida.',
            );

        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 5,
                'cantidad_planificada_salida' => 30,
            ])
            ->assertOk()
            ->json('data');
        $loteDos = $orden['lotes'][1];
        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteDos['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 6,
                'cantidad_real_salida' => 30,
                'consumos' => [
                    ['folio_id' => $folioPrincipal['id'], 'cantidad' => 30],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 3],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.folios_generados', 2)
            ->json('data');

        $orden = $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/ordenes/{$orden['id']}/lotes", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 7,
                'cantidad_planificada_salida' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('data.lotes.2.cantidad_planificada_salida', '20.000')
            ->json('data');
        $loteTres = $orden['lotes'][2];
        $this->conToken($tokenTablet)
            ->postJson("/api/materiales/transformaciones/lotes/{$loteTres['id']}/cerrar", [
                'operacion_id' => (string) Str::uuid(),
                'version_conocida' => 8,
                'cantidad_real_salida' => 20,
                'consumos' => [
                    ['folio_id' => $folioPrincipal['id'], 'cantidad' => 20],
                    ['folio_id' => $folioAuxiliar['id'], 'cantidad' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente_cierre')
            ->assertJsonPath('data.folios_generados', 3)
            ->assertJsonPath('data.folios_pendientes', 0)
            ->assertJsonCount(1, 'data.lotes.0.salidas')
            ->assertJsonCount(1, 'data.lotes.1.salidas')
            ->assertJsonCount(1, 'data.lotes.2.salidas');
    }

'''
replace(
    test_path,
    "    private function prepararLoteCerradoParaImpresion(): array\n",
    new_test + "    private function prepararLoteCerradoParaImpresion(): array\n",
)

# Migración aditiva y compatible con versiones históricas.
write(
    "database/migrations/2026_07_31_191500_agregar_unidades_por_folio_a_versiones_recetas_materiales.php",
    '''<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versiones_recetas_materiales', function (Blueprint $table) {
            $table->decimal('unidades_por_folio_salida', 14, 3)
                ->nullable()
                ->after('cantidad_base_salida');
        });
    }

    public function down(): void
    {
        Schema::table('versiones_recetas_materiales', function (Blueprint $table) {
            $table->dropColumn('unidades_por_folio_salida');
        });
    }
};
''',
)

print("Mejora de unidades por folio aplicada correctamente.")
