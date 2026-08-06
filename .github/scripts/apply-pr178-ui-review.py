from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise RuntimeError(f'No se encontró el bloque: {label}')
    return text.replace(old, new, 1)


def replace_between(text: str, start: str, end: str, replacement: str) -> str:
    start_index = text.index(start)
    end_index = text.index(end, start_index)
    return text[:start_index] + replacement + text[end_index:]


# Oficina: estructura del modal.
view_path = Path('resources/views/office/raw-material-process.blade.php')
view = view_path.read_text()
view = replace_once(
    view,
    '''                <div class="delivery-summary" id="returnSummary"></div>
                <div class="return-results-heading"><div><strong>Resultados de Packing</strong><small>Se creará un sublote interno por cada fila.</small></div><button class="secondary-button" id="addReturnResult" type="button">+ Agregar resultado</button></div>
                <div class="return-results" id="returnResults"></div>
                <label class="process-check"><input name="cierra_entrega" type="checkbox"><span><strong>Cerrar el retorno de este viaje</strong><small>Marca esta opción cuando Packing ya no devolverá más fruta de la entrega.</small></span></label>
''',
    '''                <div class="delivery-summary" id="returnSummary"></div>
                <div class="return-origins-heading"><div><strong>Viajes de origen</strong><small>Selecciona todos los viajes incluidos en este retorno físico y decide cuáles quedan cerrados.</small></div></div>
                <div class="return-origins" id="returnOrigins"></div>
                <div class="return-results-heading"><div><strong>Resultados de Packing</strong><small>Se creará un sublote interno por cada fila, sin duplicarlo entre los orígenes.</small></div><button class="secondary-button" id="addReturnResult" type="button">+ Agregar resultado</button></div>
                <div class="return-results" id="returnResults"></div>
''',
    'modal de retornos',
)
view_path.write_text(view)

# Oficina: lógica multiorigen.
js_path = Path('resources/js/office-raw-material-process.js')
js = js_path.read_text()
js = replace_once(
    js,
    "    addReturnResult: byId('addReturnResult'), returnError: byId('returnError'),\n",
    "    addReturnResult: byId('addReturnResult'), returnOrigins: byId('returnOrigins'), returnError: byId('returnError'),\n",
    'referencia returnOrigins',
)
old_movement = '''function renderReturnMovement(movement) {
    return `<article class="return-movement${movement.anulado ? ' is-void' : ''}"><div class="return-movement__heading"><div><strong>${escapeHtml(movement.numero)}${movement.cierra_entrega ? ' · Cierre' : ' · Parcial'}</strong><small>${escapeHtml(movement.registrado_por?.nombre)} · ${escapeHtml(formatDate(movement.registrado_at))}${movement.anulado ? ` · Anulado: ${escapeHtml(movement.motivo_anulacion)}` : ''}</small></div>${movement.puede_anular ? `<button data-annul-return="${escapeHtml(movement.id)}" type="button">Anular retorno</button>` : ''}</div>${movement.resultados.map(renderResult).join('')}</article>`;
}
'''
new_movement = '''function renderReturnMovement(movement) {
    const origins = movement.origenes || [];
    const originMarkup = origins.length
        ? `<div class="return-movement__origins"><strong>Orígenes:</strong> ${origins.map((origin) => `${escapeHtml(origin.numero_lote || 'Lote')} · ${escapeHtml(origin.numero_orden)}${origin.cierra_entrega ? ' (cerrado)' : ' (abierto)'}`).join(' · ')}</div>`
        : '';
    return `<article class="return-movement${movement.anulado ? ' is-void' : ''}"><div class="return-movement__heading"><div><strong>${escapeHtml(movement.numero)}${movement.cierra_entrega ? ' · Cierre del viaje' : ' · Retorno parcial'}</strong><small>${escapeHtml(movement.registrado_por?.nombre)} · ${escapeHtml(formatDate(movement.registrado_at))}${movement.anulado ? ` · Anulado: ${escapeHtml(movement.motivo_anulacion)}` : ''}</small></div>${movement.puede_anular ? `<button data-annul-return="${escapeHtml(movement.id)}" type="button">Anular retorno</button>` : ''}</div>${originMarkup}${movement.resultados.map(renderResult).join('')}</article>`;
}
'''
js = replace_once(js, old_movement, new_movement, 'movimiento con orígenes')
insert_after_results = '''function addResultRow() {
    const row = document.createElement('div'); row.className = 'return-result-row';
    row.innerHTML = `<label><span>Resultado *</span><select data-field="tipo" required><option value="">Seleccionar</option>${state.catalogs.tipos_resultado.map((type) => `<option value="${escapeHtml(type.id)}">${escapeHtml(type.nombre)}</option>`).join('')}</select></label><label><span>Nombre específico</span><input data-field="nombre" maxlength="100" placeholder="Obligatorio para Otro"></label><label><span>Bins *</span><input data-field="bins" type="number" min="1" max="100000" inputmode="numeric" required></label><label><span>Kilos netos</span><input data-field="kilos" type="number" min="0.001" max="999999999.999" step="0.001" inputmode="decimal" placeholder="Opcional"></label><button class="remove-result" type="button" aria-label="Quitar resultado">×</button>`;
    elements.returnResults.append(row);
}
'''
origin_helpers = insert_after_results + '''function eligibleReturnOrigins() {
    return state.lots.flatMap((lot) => lot.entregas
        .filter((delivery) => !delivery.anulado && delivery.retorno?.puede_registrar)
        .map((delivery) => ({ lot, delivery })));
}
function renderReturnOrigins(primaryDeliveryId) {
    const origins = eligibleReturnOrigins();
    elements.returnOrigins.innerHTML = origins.map(({ lot, delivery }) => {
        const primary = delivery.id === primaryDeliveryId;
        return `<article class="return-origin${primary ? ' is-primary' : ''}">
            <label class="return-origin__selection">
                <input data-return-origin value="${escapeHtml(delivery.id)}" type="checkbox"${primary ? ' checked disabled' : ''}>
                <span><strong>${escapeHtml(lot.numero_lote)} · ${escapeHtml(delivery.numero_orden)}</strong><small>${escapeHtml(delivery.linea_proceso)} · turno ${escapeHtml(delivery.turno)} · ${formatNumber(delivery.cantidad_envases)} bins${primary ? ' · viaje principal' : ''}</small></span>
            </label>
            <label class="return-origin__close">
                <input data-return-close value="${escapeHtml(delivery.id)}" type="checkbox">
                <span>Cerrar este viaje</span>
            </label>
        </article>`;
    }).join('');
}
function collectReturnOrigins() {
    return [...elements.returnOrigins.querySelectorAll('[data-return-origin]')]
        .filter((input) => input.checked)
        .map((input) => ({
            entrega_fruta_proceso_id: input.value,
            cierra_entrega: elements.returnOrigins.querySelector(`[data-return-close][value="${CSS.escape(input.value)}"]`)?.checked === true,
        }));
}
'''
js = replace_once(js, insert_after_results, origin_helpers, 'helpers multiorigen')
js = replace_once(
    js,
    "    state.selected = selected; elements.returnForm.reset(); elements.returnResults.innerHTML = ''; addResultRow();\n",
    "    state.selected = selected; elements.returnForm.reset(); elements.returnResults.innerHTML = ''; addResultRow(); renderReturnOrigins(deliveryId);\n",
    'abrir retorno multiorigen',
)
js = replace_once(
    js,
    "    const data = new FormData(elements.returnForm);\n    const payload = { operacion_id: uuid(), cierra_entrega: data.get('cierra_entrega') === 'on', observacion: String(data.get('observacion') || '').trim() || null, resultados: results };\n",
    "    const data = new FormData(elements.returnForm);\n    const origins = collectReturnOrigins();\n    if (!origins.length || !origins.some((origin) => origin.entrega_fruta_proceso_id === state.selected.delivery.id)) { elements.returnError.textContent = 'El retorno debe conservar el viaje principal y al menos un origen.'; return; }\n    const payload = { operacion_id: uuid(), entregas: origins, observacion: String(data.get('observacion') || '').trim() || null, resultados: results };\n",
    'payload multiorigen oficina',
)
js = replace_once(
    js,
    "elements.returnResults.addEventListener('click', (event) => { const remove = event.target.closest('.remove-result'); if (remove && elements.returnResults.children.length > 1) remove.closest('.return-result-row').remove(); });\n",
    "elements.returnResults.addEventListener('click', (event) => { const remove = event.target.closest('.remove-result'); if (remove && elements.returnResults.children.length > 1) remove.closest('.return-result-row').remove(); });\nelements.returnOrigins.addEventListener('change', (event) => {\n    const origin = event.target.closest('[data-return-origin]');\n    if (!origin) return;\n    const close = elements.returnOrigins.querySelector(`[data-return-close][value=\"${CSS.escape(origin.value)}\"]`);\n    if (close && !origin.checked) close.checked = false;\n});\n",
    'sincronía selección/cierre oficina',
)
js_path.write_text(js)

css_path = Path('resources/css/office-raw-material-process.css')
css = css_path.read_text()
css = replace_once(
    css,
    ".return-results-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 15px 0 9px; }\n",
    ".return-origins-heading,\n.return-results-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 15px 0 9px; }\n.return-origins-heading small,\n",
    'encabezado orígenes CSS',
)
css = replace_once(
    css,
    ".return-results-heading small { display: block; color: var(--muted); margin-top: 3px; }\n",
    ".return-results-heading small { display: block; color: var(--muted); margin-top: 3px; }\n.return-origins { display: grid; gap: 8px; }\n.return-origin { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 11px; background: var(--deep); padding: 11px; }\n.return-origin.is-primary { border-color: rgba(98, 224, 173, .45); }\n.return-origin__selection, .return-origin__close { display: flex; align-items: center; gap: 9px; }\n.return-origin__selection input, .return-origin__close input { width: 19px; height: 19px; accent-color: var(--process-green); }\n.return-origin__selection span { display: grid; gap: 3px; }\n.return-origin__selection small, .return-movement__origins { color: var(--muted); font-size: .64rem; }\n.return-origin__close { color: #c5d4da; font-size: .68rem; font-weight: 800; white-space: nowrap; }\n.return-movement__origins { border-top: 1px solid var(--line); margin-top: 8px; padding-top: 8px; line-height: 1.5; }\n",
    'estilos orígenes',
)
css = replace_once(
    css,
    "    .return-result-row { grid-template-columns: 1fr 1fr; }\n",
    "    .return-origin { grid-template-columns: 1fr; }\n    .return-result-row { grid-template-columns: 1fr 1fr; }\n",
    'responsive orígenes',
)
css_path.write_text(css)

# Contrato móvil.
domain_path = Path('mobile/src/domain/frutaProceso.ts')
domain = domain_path.read_text()
domain = replace_once(
    domain,
    "export type ProcessReturnMovement = {\n",
    "export type ProcessReturnOrigin = {\n  entrega_id: string;\n  lote_id: string;\n  numero_lote: string | null;\n  linea_proceso: string;\n  turno: 'A' | 'B';\n  numero_orden: string;\n  cierra_entrega: boolean;\n};\nexport type ProcessReturnMovement = {\n",
    'tipo origen móvil',
)
domain = replace_once(
    domain,
    "  cierra_entrega: boolean;\n  observacion: string | null;\n",
    "  cierra_entrega: boolean;\n  origenes: ProcessReturnOrigin[];\n  observacion: string | null;\n",
    'orígenes en movimiento móvil',
)
domain = replace_once(
    domain,
    "  sublotes_pendientes_ubicacion: number;\n};\n",
    "  sublotes_pendientes_ubicacion: number;\n  retornos_registrados: number;\n  desglose_resultados: Array<{\n    tipo: { id: string | null; codigo: string | null; nombre: string | null };\n    sublotes: number;\n    bins: number;\n    kilos: number;\n  }>;\n};\n",
    'resumen consolidado móvil',
)
domain = replace_once(
    domain,
    "  cierra_entrega: boolean;\n  observacion: string | null;\n  resultados: Array<{\n",
    "  entregas: Array<{ entrega_fruta_proceso_id: string; cierra_entrega: boolean }>;\n  observacion: string | null;\n  resultados: Array<{\n",
    'payload multiorigen móvil',
)
domain_path.write_text(domain)

screen_path = Path('mobile/src/screens/FrutaProcesoScreen.tsx')
screen = screen_path.read_text()
screen = replace_once(
    screen,
    "type ReturnResultDraft = { id: string; typeId: string; name: string; bins: string; kilos: string };\n",
    "type ReturnResultDraft = { id: string; typeId: string; name: string; bins: string; kilos: string };\ntype ReturnOriginDraft = { deliveryId: string; label: string; detail: string; selected: boolean; closes: boolean; primary: boolean };\n",
    'draft origen móvil',
)
screen = replace_once(
    screen,
    "  sublotes_pendientes_ubicacion: 0,\n};\n",
    "  sublotes_pendientes_ubicacion: 0,\n  retornos_registrados: 0,\n  desglose_resultados: [],\n};\n",
    'resumen vacío móvil',
)
screen = replace_once(
    screen,
    "  const [returnResults, setReturnResults] = useState<ReturnResultDraft[]>([]);\n  const [closeReturn, setCloseReturn] = useState(false);\n",
    "  const [returnResults, setReturnResults] = useState<ReturnResultDraft[]>([]);\n  const [returnOrigins, setReturnOrigins] = useState<ReturnOriginDraft[]>([]);\n",
    'estado orígenes móvil',
)
screen = replace_once(
    screen,
    "    setAction({ type: 'return', lot, delivery }); setOperationId(Crypto.randomUUID());\n    setReturnResults([newResult()]); setCloseReturn(false); setObservation(''); setError('');\n",
    "    setAction({ type: 'return', lot, delivery }); setOperationId(Crypto.randomUUID());\n    setReturnResults([newResult()]);\n    setReturnOrigins(lots.flatMap((originLot) => originLot.entregas\n      .filter((origin) => !origin.anulado && origin.retorno.puede_registrar)\n      .map((origin) => ({\n        deliveryId: origin.id,\n        label: `${originLot.numero_lote} · ${origin.numero_orden}`,\n        detail: `${origin.linea_proceso} · turno ${origin.turno} · ${origin.cantidad_envases} bins`,\n        selected: origin.id === delivery.id,\n        closes: false,\n        primary: origin.id === delivery.id,\n      }))));\n    setObservation(''); setError('');\n",
    'abrir retorno móvil',
)
screen = replace_once(
    screen,
    "  function updateResult(id: string, change: Partial<ReturnResultDraft>) {\n    setReturnResults((current) => current.map((item) => item.id === id ? { ...item, ...change } : item));\n  }\n",
    "  function updateResult(id: string, change: Partial<ReturnResultDraft>) {\n    setReturnResults((current) => current.map((item) => item.id === id ? { ...item, ...change } : item));\n  }\n  function toggleReturnOrigin(id: string) {\n    setReturnOrigins((current) => current.map((origin) => origin.deliveryId !== id || origin.primary\n      ? origin\n      : { ...origin, selected: !origin.selected, closes: origin.selected ? false : origin.closes }));\n  }\n  function toggleReturnOriginClose(id: string) {\n    setReturnOrigins((current) => current.map((origin) => origin.deliveryId === id && origin.selected\n      ? { ...origin, closes: !origin.closes }\n      : origin));\n  }\n",
    'actualizar orígenes móvil',
)
screen = replace_once(
    screen,
    "      setActionBusy(true);\n      try {\n        const updated = await createPackingReturn(baseUrl, auth.token, action.delivery.id, {\n          operacion_id: operationId,\n          cierra_entrega: closeReturn,\n",
    "      const selectedOrigins = returnOrigins.filter((origin) => origin.selected);\n      if (!selectedOrigins.length || !selectedOrigins.some((origin) => origin.deliveryId === action.delivery.id)) { setError('El retorno debe conservar el viaje principal y al menos un origen.'); return; }\n      setActionBusy(true);\n      try {\n        await createPackingReturn(baseUrl, auth.token, action.delivery.id, {\n          operacion_id: operationId,\n          entregas: selectedOrigins.map((origin) => ({ entrega_fruta_proceso_id: origin.deliveryId, cierra_entrega: origin.closes })),\n",
    'envío multiorigen móvil',
)
screen = replace_once(
    screen,
    "        replaceLot(updated); setAction(null); setMessage('Retorno registrado; sublotes pendientes de ubicación.');\n        setSummary(await getProcessSummary(baseUrl, auth.token));\n",
    "        setAction(null); setMessage('Retorno multiorigen registrado; sublotes pendientes de ubicación.');\n        await load();\n",
    'refresco multiorigen móvil',
)
screen = replace_once(
    screen,
    "          {action?.type === 'return' ? <ReturnForm catalogs={catalogs} closeReturn={closeReturn} delivery={action.delivery} onAdd={() => setReturnResults((current) => [...current, newResult()])} onCloseReturn={setCloseReturn} onObservation={setObservation} onRemove={(id) => setReturnResults((current) => current.length > 1 ? current.filter((item) => item.id !== id) : current)} onUpdate={updateResult} observation={observation} results={returnResults} /> : null}\n",
    "          {action?.type === 'return' ? <ReturnForm catalogs={catalogs} delivery={action.delivery} onAdd={() => setReturnResults((current) => [...current, newResult()])} onObservation={setObservation} onRemove={(id) => setReturnResults((current) => current.length > 1 ? current.filter((item) => item.id !== id) : current)} onToggleOrigin={toggleReturnOrigin} onToggleOriginClose={toggleReturnOriginClose} onUpdate={updateResult} observation={observation} origins={returnOrigins} results={returnResults} /> : null}\n",
    'formulario multiorigen móvil',
)
old_return_movement = '''function ReturnMovement({ movement, onLocate, onAnnul }: { movement: ProcessReturnMovement; onLocate: (sublot: ProcessSublot) => void; onAnnul: () => void }) {
  return <View style={[styles.returnMovement, movement.anulado && styles.deliveryVoid]}><View style={styles.lotHeading}><View><Text style={styles.deliveryQuantity}>{movement.numero} · {movement.cierra_entrega ? 'Cierre' : 'Parcial'}</Text><Text style={styles.deliveryMeta}>{movement.registrado_por?.nombre} · {formatDate(movement.registrado_at)}</Text></View>{movement.puede_anular ? <Pressable onPress={onAnnul} style={styles.annul}><Text style={styles.annulText}>Anular</Text></Pressable> : null}</View>{movement.resultados.map((result) => <View key={result.id} style={styles.resultRow}><View style={styles.deliveryText}><Text style={styles.deliveryDestination}>{result.numero_sublote} · {result.nombre_resultado}</Text><Text style={styles.deliveryMeta}>{result.cantidad_bins} bins · {formatKilos(result.kilos_netos)} · {result.camara?.codigo ?? stateLabel(result.estado)}</Text></View>{result.puede_ubicar ? <Pressable onPress={() => onLocate(result)} style={styles.returnButton}><Text style={styles.returnButtonText}>Ubicar</Text></Pressable> : null}</View>)}</View>;
}
'''
new_return_movement = '''function ReturnMovement({ movement, onLocate, onAnnul }: { movement: ProcessReturnMovement; onLocate: (sublot: ProcessSublot) => void; onAnnul: () => void }) {
  return <View style={[styles.returnMovement, movement.anulado && styles.deliveryVoid]}><View style={styles.lotHeading}><View><Text style={styles.deliveryQuantity}>{movement.numero} · {movement.cierra_entrega ? 'Cierre del viaje' : 'Retorno parcial'}</Text><Text style={styles.deliveryMeta}>{movement.registrado_por?.nombre} · {formatDate(movement.registrado_at)}</Text></View>{movement.puede_anular ? <Pressable onPress={onAnnul} style={styles.annul}><Text style={styles.annulText}>Anular</Text></Pressable> : null}</View>{movement.origenes?.length ? <Text style={styles.deliveryMeta}>Orígenes: {movement.origenes.map((origin) => `${origin.numero_lote ?? 'Lote'} · ${origin.numero_orden}${origin.cierra_entrega ? ' (cerrado)' : ' (abierto)'}`).join(' · ')}</Text> : null}{movement.resultados.map((result) => <View key={result.id} style={styles.resultRow}><View style={styles.deliveryText}><Text style={styles.deliveryDestination}>{result.numero_sublote} · {result.nombre_resultado}</Text><Text style={styles.deliveryMeta}>{result.cantidad_bins} bins · {formatKilos(result.kilos_netos)} · {result.camara?.codigo ?? stateLabel(result.estado)}</Text></View>{result.puede_ubicar ? <Pressable onPress={() => onLocate(result)} style={styles.returnButton}><Text style={styles.returnButtonText}>Ubicar</Text></Pressable> : null}</View>)}</View>;
}
'''
screen = replace_once(screen, old_return_movement, new_return_movement, 'movimiento móvil con orígenes')
return_form = '''function ReturnForm({ delivery, catalogs, results, origins, observation, onAdd, onRemove, onUpdate, onToggleOrigin, onToggleOriginClose, onObservation }: { delivery: ProcessDelivery; catalogs: ProcessCatalogs; results: ReturnResultDraft[]; origins: ReturnOriginDraft[]; observation: string; onAdd: () => void; onRemove: (id: string) => void; onUpdate: (id: string, change: Partial<ReturnResultDraft>) => void; onToggleOrigin: (id: string) => void; onToggleOriginClose: (id: string) => void; onObservation: (value: string) => void }) {
  return <><Text style={styles.eyebrow}>PACKING → CÁMARA MP</Text><Text style={styles.modalTitle}>Registrar retorno</Text><Text style={styles.modalCopy}>{delivery.cantidad_envases} bins enviados · {formatKilos(delivery.kilos_enviados)}.</Text><Text style={styles.fieldLabel}>Viajes de origen *</Text>{origins.map((origin) => <View key={origin.deliveryId} style={styles.resultDraft}><Pressable onPress={() => onToggleOrigin(origin.deliveryId)} style={[styles.checkRow, origin.selected && styles.checkRowActive]}><Text style={styles.checkMark}>{origin.selected ? '✓' : '○'}</Text><View><Text style={styles.deliveryDestination}>{origin.label}{origin.primary ? ' · principal' : ''}</Text><Text style={styles.deliveryMeta}>{origin.detail}</Text></View></Pressable>{origin.selected ? <Pressable onPress={() => onToggleOriginClose(origin.deliveryId)} style={[styles.checkRow, origin.closes && styles.checkRowActive]}><Text style={styles.checkMark}>{origin.closes ? '✓' : '○'}</Text><View><Text style={styles.deliveryDestination}>Cerrar este viaje</Text><Text style={styles.deliveryMeta}>Packing no devolverá más fruta de este origen.</Text></View></Pressable> : null}</View>)}<Text style={styles.fieldLabel}>Resultados de Packing *</Text>{results.map((result, index) => <View key={result.id} style={styles.resultDraft}><View style={styles.lotHeading}><Text style={styles.deliveryQuantity}>Resultado {index + 1}</Text>{results.length > 1 ? <Pressable onPress={() => onRemove(result.id)}><Text style={styles.annulText}>Quitar</Text></Pressable> : null}</View><Text style={styles.fieldLabel}>Clasificación *</Text><ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.typeScroller}>{catalogs.tipos_resultado.map((type) => <FilterButton active={result.typeId === type.id} key={type.id} label={type.nombre} onPress={() => onUpdate(result.id, { typeId: type.id })} />)}</ScrollView><Field label="Nombre específico"><TextInput onChangeText={(value) => onUpdate(result.id, { name: value })} placeholder="Obligatorio para Otro" placeholderTextColor={colors.muted} style={styles.input} value={result.name} /></Field><Field label="Cantidad de bins *"><TextInput keyboardType="number-pad" onChangeText={(value) => onUpdate(result.id, { bins: value })} placeholder="0" placeholderTextColor={colors.muted} style={styles.input} value={result.bins} /></Field><Field label="Kilos netos"><TextInput keyboardType="decimal-pad" onChangeText={(value) => onUpdate(result.id, { kilos: value })} placeholder="Opcional" placeholderTextColor={colors.muted} style={styles.input} value={result.kilos} /></Field></View>)}<Pressable onPress={onAdd} style={styles.secondary}><Text style={styles.secondaryText}>+ Agregar resultado</Text></Pressable><Field label="Observación"><TextInput multiline onChangeText={onObservation} placeholder="Opcional" placeholderTextColor={colors.muted} style={[styles.input, styles.textarea]} value={observation} /></Field></>;
}
'''
screen = replace_between(screen, 'function ReturnForm(', '\nfunction LocateForm(', return_form)
screen_path.write_text(screen)
