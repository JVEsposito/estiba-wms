const labelElements = {
    workspace: document.getElementById('materialLabelWorkspace'),
    form: document.getElementById('materialLabelForm'),
    error: document.getElementById('materialLabelError'),
    summary: document.getElementById('materialLabelSummary'),
    source: document.getElementById('materialLabelSource'),
    reception: document.getElementById('materialLabelReception'),
    profile: document.getElementById('materialLabelProfile'),
    folios: document.getElementById('materialLabelFolios'),
    selectAll: document.getElementById('selectAllMaterialLabels'),
    history: document.getElementById('materialLabelHistory'),
    reload: document.getElementById('reloadMaterialLabels'),
};
const labelState = {
    receptions: [],
    orders: [],
    profiles: [],
    selected: null,
    source: 'recepcion',
    history: [],
    loading: false,
};

function labelToken() {
    return localStorage.getItem('estiba_wms_office_token');
}

function labelIdentity() {
    try {
        return JSON.parse(localStorage.getItem('estiba_wms_office_identity') || 'null');
    } catch {
        return null;
    }
}

function labelEscape(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function labelUuid() {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

async function labelApi(path) {
    const response = await fetch(path, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${labelToken()}`,
        },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'No fue posible consultar las etiquetas.');
    }
    return data;
}

function receiptFolios(receipt) {
    return (receipt?.detalles || []).flatMap((detail) => (detail.bultos || [])
        .filter((packageItem) => packageItem.folio)
        .map((packageItem) => ({
            ...packageItem.folio,
            cantidad: packageItem.cantidad,
            lote_proveedor: packageItem.lote_proveedor,
            item: detail.item,
            unidad_medida: detail.unidad_medida,
            bloqueado: packageItem.bloqueado,
        })));
}

function transformationFolios(order) {
    return (order?.lotes || []).filter((lot) => lot.estado === 'cerrado')
        .flatMap((lot) => (lot.salidas || []).map((output) => ({
            id: output.folio_id,
            numero_folio: output.numero_folio,
            cantidad: output.cantidad_producida,
            lote_proveedor: `Lote ${lot.numero_lote}`,
            item: output.item,
            unidad_medida: output.item?.unidad_medida,
            bloqueado: false,
        })));
}

function selectedFolios() {
    return labelState.source === 'transformacion'
        ? transformationFolios(labelState.selected)
        : receiptFolios(labelState.selected);
}

function renderSourceOptions() {
    const placeholder = labelState.source === 'transformacion'
        ? 'Seleccionar orden de transformación'
        : 'Seleccionar recepción';
    const records = labelState.source === 'transformacion'
        ? labelState.orders.map((order) => ({
            id: order.id,
            label: `OT-${order.id.slice(0, 8).toUpperCase()} · ${order.cliente?.codigo || '—'} · ${order.version_receta?.receta?.nombre || 'Transformación'}`,
        }))
        : labelState.receptions.map((receipt) => ({
            id: receipt.id,
            label: `${receipt.numero_guia_despacho} · ${receipt.cliente?.codigo || '—'} · ${new Date(receipt.confirmado_at).toLocaleDateString('es-CL')}`,
        }));
    labelElements.reception.innerHTML = `<option value="">${placeholder}</option>`
        + records.map((record) => `
            <option value="${record.id}">${labelEscape(record.label)}</option>
        `).join('');
}

function renderProfileOptions() {
    labelElements.profile.innerHTML = labelState.profiles.map((profile) => `
        <option value="${profile.id}"${profile.predeterminado ? ' selected' : ''}>${labelEscape(profile.nombre)} · ${labelEscape(profile.fabricante)} · ${labelEscape(profile.ancho_mm)}×${labelEscape(profile.alto_mm)} mm</option>
    `).join('') || '<option value="">No existen perfiles activos</option>';
}

function renderFolios() {
    const folios = selectedFolios();
    const generated = new Set(labelState.history.flatMap((job) => job.folios.map((folio) => folio.id)));
    const reference = labelState.source === 'transformacion'
        ? `OT-${labelState.selected?.id?.slice(0, 8).toUpperCase() || ''}`
        : labelState.selected?.numero_guia_despacho;
    labelElements.summary.textContent = labelState.selected
        ? `${reference} · ${folios.length} folios disponibles`
        : 'Selecciona una recepción confirmada u orden con lotes cerrados';
    labelElements.folios.innerHTML = folios.map((folio) => `
        <label class="material-label-folio">
            <input name="folio_ids[]" type="checkbox" value="${folio.id}">
            <span><strong>${labelEscape(folio.numero_folio)}${generated.has(folio.id) ? ' · reimpresión' : ''}</strong><small>${labelEscape(folio.item?.codigo)} · ${labelEscape(folio.item?.nombre)}</small><small>${labelEscape(folio.cantidad)} ${labelEscape(folio.unidad_medida)} · lote ${labelEscape(folio.lote_proveedor || '—')}${folio.bloqueado ? ' · BLOQUEADO' : ''}</small></span>
        </label>
    `).join('') || '<p class="empty-state">El origen seleccionado no posee folios etiquetables.</p>';
    labelElements.selectAll.checked = false;
}

function renderHistory() {
    labelElements.history.innerHTML = labelState.history.map((job) => `
        <article class="material-row">
            <div><strong>${labelEscape(job.formato.toUpperCase())} · ${job.folios.length} folios × ${job.copias} copias</strong><small>${new Date(job.solicitado_at).toLocaleString('es-CL')} · ${labelEscape(job.solicitado_por)} · ${labelEscape(job.perfil?.nombre)}</small>${job.motivo_reimpresion ? `<small>Motivo: ${labelEscape(job.motivo_reimpresion)}</small>` : ''}</div>
            <span class="material-import-action">${labelEscape(job.estado)}</span>
        </article>
    `).join('') || '<p class="empty-state">Aún no se han generado etiquetas para este origen.</p>';
}

async function selectSourceRecord(id) {
    labelState.selected = null;
    labelState.history = [];
    renderFolios();
    renderHistory();
    if (!id) return;
    const prefix = labelState.source === 'transformacion'
        ? '/api/materiales/transformaciones/ordenes'
        : '/api/materiales/recepciones';
    const [record, history] = await Promise.all([
        labelApi(`${prefix}/${encodeURIComponent(id)}`),
        labelApi(`${prefix}/${encodeURIComponent(id)}/impresiones`),
    ]);
    labelState.selected = record.data;
    labelState.history = history.data || [];
    renderFolios();
    renderHistory();
}

async function loadLabels() {
    const identity = labelIdentity();
    const enabled = identity?.puede_imprimir_etiquetas_materiales === true
        || identity?.capacidades?.puede_imprimir_etiquetas_materiales === true;
    labelElements.workspace.classList.toggle('is-hidden', !enabled);
    if (!enabled || !labelToken() || labelState.loading) return;
    labelState.loading = true;
    labelElements.error.textContent = '';
    try {
        const [receptions, orders, profiles] = await Promise.all([
            labelApi('/api/materiales/recepciones?estado=confirmada&per_page=100'),
            labelApi('/api/materiales/transformaciones/ordenes?per_page=100'),
            labelApi('/api/materiales/recepciones/perfiles-impresion'),
        ]);
        labelState.receptions = receptions.data || [];
        labelState.orders = (orders.data || []).filter((order) =>
            (order.lotes || []).some((lot) => lot.estado === 'cerrado' && (lot.salidas || []).length));
        labelState.profiles = profiles.data || [];
        renderSourceOptions();
        renderProfileOptions();
        labelState.selected = null;
        labelState.history = [];
        renderFolios();
        renderHistory();
    } catch (error) {
        labelElements.error.textContent = error.message;
    } finally {
        labelState.loading = false;
    }
}

labelElements.reception?.addEventListener('change', async (event) => {
    labelElements.error.textContent = '';
    try {
        await selectSourceRecord(event.target.value);
    } catch (error) {
        labelElements.error.textContent = error.message;
    }
});

labelElements.source?.addEventListener('change', (event) => {
    labelState.source = event.target.value;
    labelState.selected = null;
    labelState.history = [];
    renderSourceOptions();
    renderFolios();
    renderHistory();
});

labelElements.selectAll?.addEventListener('change', (event) => {
    labelElements.folios.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.checked = event.target.checked;
    });
});

labelElements.form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    labelElements.error.textContent = '';
    const form = new FormData(labelElements.form);
    const folioIds = [...labelElements.folios.querySelectorAll('input[type="checkbox"]:checked')]
        .map((checkbox) => checkbox.value);
    if (!labelState.selected || folioIds.length === 0) {
        labelElements.error.textContent = 'Selecciona un origen y al menos un folio.';
        return;
    }
    const payload = {
        operacion_id: labelUuid(),
        perfil_id: form.get('perfil_id'),
        formato: form.get('formato'),
        canal: 'oficina_descarga',
        folio_ids: folioIds,
        copias: Number(form.get('copias')),
        motivo_reimpresion: String(form.get('motivo_reimpresion') || '').trim() || null,
    };
    try {
        const prefix = labelState.source === 'transformacion'
            ? '/api/materiales/transformaciones/ordenes'
            : '/api/materiales/recepciones';
        const response = await fetch(`${prefix}/${encodeURIComponent(labelState.selected.id)}/etiquetas`, {
            method: 'POST',
            headers: {
                Accept: payload.formato === 'pdf' ? 'application/pdf' : 'application/zpl',
                Authorization: `Bearer ${labelToken()}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(Object.values(data?.errors || {}).flat()[0] || data?.message || 'No fue posible generar las etiquetas.');
        }
        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const filename = disposition.match(/filename="([^"]+)"/)?.[1] || `etiquetas.${payload.formato}`;
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        labelElements.form.elements.motivo_reimpresion.value = '';
        await selectSourceRecord(labelState.selected.id);
    } catch (error) {
        labelElements.error.textContent = error.message;
    }
});

labelElements.reload?.addEventListener('click', () => void loadLabels());
window.addEventListener('estiba:office-session', (event) => {
    if (event.detail?.authenticated) void loadLabels();
    else labelElements.workspace.classList.add('is-hidden');
});

if (labelElements.form) void loadLabels();
