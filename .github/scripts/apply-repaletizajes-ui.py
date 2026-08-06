from pathlib import Path


def write(path: str, content: str) -> None:
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content)


def replace(path: str, old: str, new: str) -> None:
    target = Path(path)
    text = target.read_text()
    if old not in text:
        raise RuntimeError(f'No se encontró patrón en {path}: {old[:100]}')
    target.write_text(text.replace(old, new, 1))


write('resources/views/office/repalletizing.blade.php', r'''<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#07151e">
        <meta name="color-scheme" content="dark">
        <title>Estiba WMS · Repaletizajes</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/office.css', 'resources/css/office-repalletizing.css', 'resources/js/office-repalletizing.js'])
        @endif
    </head>
    <body>
        <section class="office-access" id="officeAccess" aria-labelledby="officeAccessTitle">
            <div class="office-access__brand repa-access-brand">
                <div class="office-logo" aria-hidden="true">⇄</div>
                <p class="eyebrow">ESTIBA WMS · REPALETIZAJES</p>
                <h1 id="officeAccessTitle">Consolida saldos sin perder su composición.</h1>
                <p>El resultado puede ser pallet o saldo, conservar un folio o recibir otro número escrito o escaneado.</p>
                <div class="feature-row"><span>Genealogía</span><span>MIX visible</span><span>Estado térmico protegido</span></div>
            </div>
            <form class="office-access__form" id="officeLoginForm" novalidate>
                <div><p class="eyebrow">ACCESO DE OFICINA</p><h2>Ingresar a repaletizajes</h2></div>
                <label><span>Correo electrónico</span><input name="email" type="email" autocomplete="username" required></label>
                <label><span>Contraseña</span><input name="password" type="password" autocomplete="current-password" required></label>
                <p class="form-error" id="officeLoginError" role="alert"></p>
                <button class="primary-button" type="submit">Entrar <span>→</span></button>
            </form>
        </section>

        <main class="office-app is-hidden" id="officeApp">
            <x-office.navigation domain="frigorifico" office="repaletizajes" context="FRIGORÍFICO · PT" icon="⇄" />

            <section class="repa-workspace">
                <header class="repa-heading">
                    <div><p class="eyebrow">CONSOLIDACIÓN DE SALDOS</p><h1>Repaletizajes</h1><p>Cliente, especie, marca y estado térmico nunca se mezclan. El resto se advierte y queda trazado como MIX.</p></div>
                    <button class="secondary-button" id="reloadButton" type="button">↻ Actualizar</button>
                </header>

                <div class="repa-layout">
                    <section class="panel repa-builder" id="builderPanel">
                        <div class="repa-panel-heading"><div><p class="eyebrow">NUEVA REPA</p><h2>Configurar resultado</h2></div><span id="sourceCount">0 saldos</span></div>
                        <form id="repaForm" novalidate>
                            <div class="repa-grid">
                                <label><span>Tipo de resultado *</span><select name="tipo_resultado"><option value="pallet">Pallet completo</option><option value="saldo">Saldo consolidado</option></select></label>
                                <label><span>Identificación *</span><select name="estrategia_folio"><option value="conservar">Conservar un folio participante</option><option value="nuevo">Escribir o escanear otro folio</option></select></label>
                                <label id="keptFolioField"><span>Folio que se conserva *</span><select name="folio_conservado_id"><option value="">Agrega primero los saldos</option></select></label>
                                <label class="is-hidden" id="newFolioField"><span>Folio resultante *</span><input name="numero_folio_resultante" maxlength="80" autocomplete="off" placeholder="Escanear o escribir"></label>
                                <label><span>Capacidad del pallet</span><input name="cantidad_objetivo" type="number" min="2" max="100000" value="120"><small>Obligatoria para pallet; en saldo solo controla que no alcance la capacidad completa.</small></label>
                                <label><span>Observación</span><textarea name="observacion" maxlength="2000" rows="3"></textarea></label>
                            </div>

                            <div class="source-entry">
                                <label><span>Agregar saldo por folio</span><input id="sourceFolioInput" maxlength="80" autocomplete="off" placeholder="Escanear o escribir folio"></label>
                                <button class="secondary-button" id="addSourceButton" type="button">+ Agregar saldo</button>
                            </div>
                            <p class="form-error" id="sourceError" role="alert"></p>

                            <div class="hard-rule" id="hardRule"><strong>Compatibilidad obligatoria</strong><span>Cliente · especie · marca · estado térmico</span></div>
                            <div class="mix-warnings is-hidden" id="mixWarnings"></div>
                            <div class="source-list" id="sourceList"><p class="empty-copy">Agrega al menos dos folios tipo saldo.</p></div>

                            <section class="result-preview" id="resultPreview">
                                <div><span>RESULTADO</span><strong id="previewFolio">Sin definir</strong></div>
                                <div><span>TIPO</span><strong id="previewType">Pallet completo</strong></div>
                                <div><span>CAJAS</span><strong id="previewQuantity">0 / 120</strong></div>
                                <div><span>ESTADO TÉRMICO</span><strong id="previewThermal">—</strong></div>
                            </section>
                            <div class="spec-preview" id="specPreview"></div>
                            <p class="form-error" id="repaError" role="alert"></p>
                            <div class="repa-actions"><button class="secondary-button" id="clearButton" type="button">Limpiar</button><button class="primary-button" type="submit">Confirmar repaletizaje</button></div>
                        </form>
                    </section>

                    <section class="panel repa-history">
                        <div class="repa-panel-heading"><div><p class="eyebrow">TRAZABILIDAD</p><h2>Repas recientes</h2></div><input id="historyFilter" maxlength="80" placeholder="Buscar folio"></div>
                        <div class="repa-history-list" id="historyList"></div>
                    </section>
                </div>
            </section>
        </main>
        <div class="loading is-hidden" id="officeLoading" aria-hidden="true"><span></span><strong id="officeLoadingText">Procesando…</strong></div>
        <div class="toast-region" id="officeToasts" aria-live="polite"></div>
    </body>
</html>
''')

write('resources/css/office-repalletizing.css', r''':root { --repa-green: #62e0ad; --repa-gold: #f0c66d; --repa-red: #ff9494; }
body { background: radial-gradient(circle at 82% -8%, rgba(98, 224, 173, .12), transparent 32rem), var(--bg); }
.repa-access-brand { background: linear-gradient(145deg, rgba(15, 72, 61, .97), rgba(4, 15, 21, .99)); }
.repa-workspace { padding: 26px; }
.repa-heading, .repa-panel-heading, .source-entry, .repa-actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.repa-heading { align-items: flex-end; margin-bottom: 18px; }
.repa-heading h1 { margin: 0; font-size: clamp(2.2rem, 3vw, 3.3rem); letter-spacing: -.04em; }
.repa-heading p:last-child { margin: 6px 0 0; color: var(--muted); }
.repa-layout { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(340px, .75fr); gap: 15px; }
.repa-builder, .repa-history { padding: 20px; }
.repa-panel-heading { border-bottom: 1px solid var(--line); padding-bottom: 14px; margin-bottom: 15px; }
.repa-panel-heading h2 { margin: 0; }
.repa-panel-heading > span { color: var(--repa-green); font-weight: 900; }
.repa-panel-heading input { min-height: 40px; width: 180px; }
.repa-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 11px; }
.repa-grid label, .source-entry label { display: grid; gap: 6px; }
.repa-grid span, .source-entry span { color: #c5d4da; font-size: .72rem; font-weight: 800; }
.repa-grid input, .repa-grid select, .repa-grid textarea, .source-entry input, .repa-panel-heading input { width: 100%; border: 1px solid var(--line); border-radius: 10px; background: #091a23; color: var(--text); padding: 10px 12px; font: inherit; }
.repa-grid input, .repa-grid select, .source-entry input { min-height: 44px; }
.repa-grid textarea { resize: vertical; }
.repa-grid small { color: var(--muted); font-size: .62rem; }
.source-entry { align-items: end; border-top: 1px solid var(--line); margin-top: 16px; padding-top: 16px; }
.source-entry label { flex: 1; }
.source-entry button { min-height: 44px; }
.hard-rule { display: flex; justify-content: space-between; gap: 12px; border: 1px solid rgba(98, 224, 173, .28); border-radius: 10px; background: rgba(98, 224, 173, .07); margin-top: 13px; padding: 10px 12px; font-size: .7rem; }
.hard-rule span { color: var(--muted); }
.hard-rule.is-invalid { border-color: rgba(255, 148, 148, .5); background: rgba(255, 148, 148, .08); color: var(--repa-red); }
.mix-warnings { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
.mix-warnings span { border: 1px solid rgba(240, 198, 109, .38); border-radius: 999px; color: var(--repa-gold); padding: 6px 9px; font-size: .62rem; font-weight: 900; }
.source-list { display: grid; gap: 8px; margin-top: 13px; }
.source-card { display: grid; grid-template-columns: 1.2fr repeat(3, minmax(95px, .55fr)) auto; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 11px; background: var(--deep); padding: 10px; }
.source-card strong, .source-card small { display: block; }
.source-card small { color: var(--muted); margin-top: 3px; font-size: .62rem; }
.source-card label { display: grid; gap: 4px; color: var(--muted); font-size: .58rem; }
.source-card input { width: 100%; min-height: 38px; border: 1px solid var(--line); border-radius: 8px; background: #091a23; color: var(--text); padding: 8px; }
.source-card .source-value { font-weight: 900; }
.source-card button { border: 1px solid rgba(255, 148, 148, .4); border-radius: 8px; background: transparent; color: var(--repa-red); min-height: 38px; }
.empty-copy { color: var(--muted); text-align: center; padding: 24px; }
.result-preview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 15px; }
.result-preview div, .spec-preview div { border: 1px solid var(--line); border-radius: 9px; background: var(--raised); padding: 10px; }
.result-preview span, .spec-preview span { display: block; color: var(--muted); font-size: .56rem; font-weight: 900; }
.result-preview strong, .spec-preview strong { display: block; margin-top: 5px; font-size: .78rem; overflow-wrap: anywhere; }
.spec-preview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 7px; margin-top: 8px; }
.spec-preview strong.is-mix { color: var(--repa-gold); }
.repa-actions { justify-content: flex-end; border-top: 1px solid var(--line); margin-top: 15px; padding-top: 15px; }
.repa-history-list { display: grid; gap: 9px; max-height: calc(100vh - 270px); overflow: auto; }
.repa-history-card { border: 1px solid var(--line); border-radius: 11px; background: var(--deep); padding: 12px; }
.repa-history-card.is-void { opacity: .58; }
.repa-history-card header { display: flex; justify-content: space-between; gap: 9px; }
.repa-history-card h3 { margin: 0; font-size: .95rem; }
.repa-history-card header span { color: var(--repa-green); font-size: .62rem; font-weight: 900; text-transform: uppercase; }
.repa-history-card p { color: var(--muted); margin: 7px 0; font-size: .68rem; }
.repa-history-card details { border-top: 1px solid var(--line); padding-top: 8px; }
.repa-history-card summary { cursor: pointer; color: var(--muted); font-size: .65rem; font-weight: 900; }
.repa-origin { display: flex; justify-content: space-between; gap: 8px; margin-top: 5px; font-size: .65rem; }
.repa-history-card button { margin-top: 9px; min-height: 34px; border: 1px solid rgba(255, 148, 148, .4); border-radius: 8px; background: transparent; color: var(--repa-red); }
@media (max-width: 1100px) { .repa-layout { grid-template-columns: 1fr; } .repa-history-list { max-height: none; } }
@media (max-width: 760px) { .repa-workspace { padding: 14px; } .repa-heading { align-items: stretch; flex-direction: column; } .repa-grid, .result-preview, .spec-preview { grid-template-columns: 1fr 1fr; } .source-card { grid-template-columns: 1fr 1fr; } .source-card > div:first-child { grid-column: 1 / -1; } }
''')

write('resources/js/office-repalletizing.js', r'''const byId = (id) => document.getElementById(id);
const el = {
    access: byId('officeAccess'), app: byId('officeApp'), login: byId('officeLoginForm'), loginError: byId('officeLoginError'),
    logout: byId('officeLogoutButton'), userName: byId('officeUserName'), userRole: byId('officeUserRole'), initials: byId('officeInitials'),
    reload: byId('reloadButton'), form: byId('repaForm'), sourceInput: byId('sourceFolioInput'), addSource: byId('addSourceButton'),
    sourceError: byId('sourceError'), repaError: byId('repaError'), sourceList: byId('sourceList'), sourceCount: byId('sourceCount'),
    keptField: byId('keptFolioField'), newField: byId('newFolioField'), hardRule: byId('hardRule'), mixWarnings: byId('mixWarnings'),
    previewFolio: byId('previewFolio'), previewType: byId('previewType'), previewQuantity: byId('previewQuantity'), previewThermal: byId('previewThermal'),
    specPreview: byId('specPreview'), clear: byId('clearButton'), history: byId('historyList'), historyFilter: byId('historyFilter'),
    loading: byId('officeLoading'), loadingText: byId('officeLoadingText'), toasts: byId('officeToasts'),
};
const keys = { token: 'estiba_wms_office_token', identity: 'estiba_wms_office_identity' };
const state = { token: localStorage.getItem(keys.token), identity: readJson(keys.identity), sources: [], history: [] };
class ApiError extends Error { constructor(message, status = 0) { super(message); this.status = status; } }
function readJson(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } }
function escapeHtml(value) { return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function uuid() { if (crypto.randomUUID) return crypto.randomUUID(); return `${Date.now()}-${Math.random()}`; }
function errorMessage(data, fallback) { return Object.values(data?.errors || {}).flat()[0] || data?.message || fallback; }
function setBusy(active, text = 'Procesando…') { el.loadingText.textContent = text; el.loading.classList.toggle('is-hidden', !active); el.loading.setAttribute('aria-hidden', String(!active)); }
function toast(message, error = false) { const item = document.createElement('div'); item.className = `toast${error ? ' toast--error' : ''}`; item.textContent = message; el.toasts.append(item); setTimeout(() => item.remove(), 5000); }
async function api(path, options = {}) { const headers = new Headers(options.headers || {}); headers.set('Accept', 'application/json'); if (state.token) headers.set('Authorization', `Bearer ${state.token}`); if (options.body) headers.set('Content-Type', 'application/json'); let response; try { response = await fetch(path, { ...options, headers }); } catch { throw new ApiError('No fue posible conectar con Laravel.'); } const data = response.status === 204 ? null : await response.json().catch(() => ({})); if (!response.ok) { if (response.status === 401 && path !== '/api/acceso-oficina') clearSession(); throw new ApiError(errorMessage(data, 'No fue posible completar la operación.'), response.status); } return data; }
function persist(payload) { state.token = payload.token; state.identity = payload.usuario; localStorage.setItem(keys.token, payload.token); localStorage.setItem(keys.identity, JSON.stringify(payload.usuario)); }
function clearSession() { state.token = null; state.identity = null; localStorage.removeItem(keys.token); localStorage.removeItem(keys.identity); el.app.classList.add('is-hidden'); el.access.classList.remove('is-hidden'); }
function can(key) { return state.identity?.[key] === true || state.identity?.capacidades?.[key] === true; }
function showApp() { if (!can('puede_consultar_repaletizajes')) return false; el.access.classList.add('is-hidden'); el.app.classList.remove('is-hidden'); const name = state.identity?.nombre || 'Usuario'; el.userName.textContent = name; el.userRole.textContent = String(state.identity?.rol || 'Oficina').replaceAll('_', ' '); el.initials.textContent = name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase(); el.form.querySelector('button[type="submit"]').disabled = !can('puede_operar_repaletizajes'); return true; }
function formValue(name) { return el.form.elements[name]?.value ?? ''; }
function normalized(value) { return String(value ?? '').trim().toLocaleUpperCase('es-CL'); }
const hardFields = [['cliente', 'cliente'], ['especie', 'especie'], ['marca', 'marca'], ['condicion_termica', 'estado térmico']];
const mixFields = [['variedad', 'Variedad'], ['calibre', 'Calibre'], ['envase', 'Envase'], ['categoria', 'Categoría'], ['csg', 'CSG'], ['predio', 'Predio'], ['cuartel', 'Cuartel']];
function common(field) { const values = [...new Set(state.sources.map((source) => normalized(source[field])))]; return values.length <= 1 ? state.sources[0]?.[field] ?? '—' : 'MIX'; }
function hardMismatch() { if (state.sources.length < 2) return []; return hardFields.filter(([field]) => new Set(state.sources.map((source) => normalized(source[field]))).size > 1); }
function total() { return state.sources.reduce((sum, source) => sum + Number(source.aporte || 0), 0); }
function remainingCapacity() { const target = Number(formValue('cantidad_objetivo') || 0); return Math.max(0, target - total()); }
function resultNumber() { if (formValue('estrategia_folio') === 'conservar') { const source = state.sources.find((item) => item.id === formValue('folio_conservado_id')); return source?.numero_folio || ''; } return normalized(formValue('numero_folio_resultante')); }
function render() {
    const strategy = formValue('estrategia_folio'); const resultType = formValue('tipo_resultado'); const target = Number(formValue('cantidad_objetivo') || 0);
    el.keptField.classList.toggle('is-hidden', strategy !== 'conservar'); el.newField.classList.toggle('is-hidden', strategy !== 'nuevo');
    const kept = el.form.elements.folio_conservado_id; const current = kept.value; kept.innerHTML = `<option value="">Seleccionar</option>${state.sources.map((source) => `<option value="${escapeHtml(source.id)}">${escapeHtml(source.numero_folio)} · ${source.cantidad_cajas} cajas</option>`).join('')}`; if (state.sources.some((source) => source.id === current)) kept.value = current; else if (state.sources.length) kept.value = state.sources[0].id;
    el.sourceCount.textContent = `${state.sources.length} saldo${state.sources.length === 1 ? '' : 's'}`;
    el.sourceList.innerHTML = state.sources.length ? state.sources.map((source) => `<article class="source-card"><div><strong>${escapeHtml(source.numero_folio)}</strong><small>${escapeHtml(source.cliente)} · ${escapeHtml(source.especie)} · ${escapeHtml(source.marca)}</small><small>${escapeHtml(source.calibre)} · CSG ${escapeHtml(source.csg)} · ${escapeHtml(source.condicion_termica)}</small></div><label>DISPONIBLE<span class="source-value">${source.cantidad_cajas}</span></label><label>APORTA<input data-contribution="${escapeHtml(source.id)}" type="number" min="1" max="${source.cantidad_cajas}" value="${source.aporte}"></label><label>QUEDA<span class="source-value">${Math.max(0, source.cantidad_cajas - source.aporte)}</span></label><button data-remove="${escapeHtml(source.id)}" type="button">Quitar</button></article>`).join('') : '<p class="empty-copy">Agrega al menos dos folios tipo saldo.</p>';
    const mismatches = hardMismatch(); el.hardRule.classList.toggle('is-invalid', mismatches.length > 0); el.hardRule.querySelector('strong').textContent = mismatches.length ? `INCOMPATIBLE: ${mismatches.map(([, label]) => label).join(', ')}` : 'Compatibilidad obligatoria';
    const mixes = mixFields.filter(([field]) => state.sources.length > 1 && common(field) === 'MIX'); el.mixWarnings.classList.toggle('is-hidden', !mixes.length); el.mixWarnings.innerHTML = mixes.map(([, label]) => `<span>⚠ MIX ${escapeHtml(label.toUpperCase())}</span>`).join('');
    el.previewFolio.textContent = resultNumber() || 'Sin definir'; el.previewType.textContent = resultType === 'pallet' ? 'Pallet completo' : 'Saldo consolidado'; el.previewQuantity.textContent = resultType === 'pallet' ? `${total()} / ${target || '—'}` : `${total()} cajas`; el.previewThermal.textContent = state.sources.length ? common('condicion_termica') : '—';
    const specs = [['Cliente', common('cliente')], ['Especie', common('especie')], ['Marca', common('marca')], ...mixFields.map(([field, label]) => [label, common(field)])]; el.specPreview.innerHTML = state.sources.length ? specs.map(([label, value]) => `<div><span>${escapeHtml(label.toUpperCase())}</span><strong class="${value === 'MIX' ? 'is-mix' : ''}">${escapeHtml(value || '—')}</strong></div>`).join('') : '';
}
async function addSource() { const number = normalized(el.sourceInput.value); el.sourceError.textContent = ''; if (!number) { el.sourceError.textContent = 'Escanea o escribe un folio.'; return; } if (state.sources.some((source) => source.numero_folio === number)) { el.sourceError.textContent = 'Ese folio ya fue agregado.'; return; } setBusy(true, 'Buscando saldo…'); try { const source = await api(`/api/validacion/repaletizajes/folios/${encodeURIComponent(number)}`); if (!source.existe) throw new ApiError('El folio no existe.'); if (!source.activo || source.tipo_bulto !== 'saldo' || source.cantidad_cajas < 1) throw new ApiError('El folio no es un saldo activo con cajas disponibles.'); if (!['pendiente_prefrio', 'prefrio_aprobado'].includes(source.condicion_termica)) throw new ApiError('El folio posee un estado térmico transitorio o retenido.'); const amount = formValue('tipo_resultado') === 'pallet' ? Math.min(source.cantidad_cajas, remainingCapacity() || source.cantidad_cajas) : source.cantidad_cajas; state.sources.push({ ...source, aporte: amount }); el.sourceInput.value = ''; render(); } catch (error) { el.sourceError.textContent = error.message; } finally { setBusy(false); } }
function reset() { state.sources = []; el.form.reset(); el.form.elements.cantidad_objetivo.value = 120; el.sourceError.textContent = ''; el.repaError.textContent = ''; render(); }
async function submit() { el.repaError.textContent = ''; if (state.sources.length < 2) { el.repaError.textContent = 'Agrega al menos dos saldos.'; return; } if (hardMismatch().length) { el.repaError.textContent = 'Cliente, especie, marca y estado térmico deben ser idénticos.'; return; } const type = formValue('tipo_resultado'); const target = Number(formValue('cantidad_objetivo') || 0); if (type === 'pallet' && (!target || total() !== target)) { el.repaError.textContent = `El pallet debe completar exactamente ${target || 'la capacidad indicada'} cajas.`; return; } if (type === 'saldo' && target && total() >= target) { el.repaError.textContent = 'Un saldo debe quedar bajo la capacidad completa.'; return; } const strategy = formValue('estrategia_folio'); const number = resultNumber(); if (!number) { el.repaError.textContent = 'Define el folio resultante.'; return; } const kept = strategy === 'conservar' ? formValue('folio_conservado_id') : null; if (kept) { const source = state.sources.find((item) => item.id === kept); if (source && source.aporte !== source.cantidad_cajas) { el.repaError.textContent = 'El folio conservado debe aportar todas sus cajas.'; return; } } setBusy(true, 'Confirmando repaletizaje…'); try { const response = await api('/api/validacion/repaletizajes', { method: 'POST', body: JSON.stringify({ operacion_id: uuid(), tipo_resultado: type, estrategia_folio: strategy, numero_folio_resultante: number, folio_conservado_id: kept, cantidad_objetivo: target || null, origenes: state.sources.map((source) => ({ folio_id: source.id, cantidad_aportada: Number(source.aporte) })), observacion: String(formValue('observacion') || '').trim() || null }) }); toast(`${response.data.codigo}: ${response.data.folio_resultante.numero_folio} confirmado.`); reset(); await loadHistory(); } catch (error) { el.repaError.textContent = error.message; } finally { setBusy(false); } }
function renderHistory() { el.history.innerHTML = state.history.length ? state.history.map((repa) => `<article class="repa-history-card${repa.estado === 'anulado' ? ' is-void' : ''}"><header><h3>${escapeHtml(repa.codigo)} · ${escapeHtml(repa.folio_resultante?.numero_folio)}</h3><span>${escapeHtml(repa.estado)}</span></header><p>${repa.tipo_resultado === 'pallet' ? 'Pallet completo' : 'Saldo'} · ${repa.cantidad_resultante} cajas · ${escapeHtml(repa.condicion_termica)}</p>${repa.advertencias?.length ? `<p>⚠ ${repa.advertencias.map((item) => escapeHtml(item.campo)).join(' · ')}</p>` : ''}<details><summary>Composición (${repa.origenes.length})</summary>${repa.origenes.map((origin) => `<div class="repa-origin"><span>${escapeHtml(origin.folio.numero_folio)}</span><strong>${origin.cajas_aportadas} cajas</strong></div>`).join('')}</details>${can('puede_anular_repaletizajes') && repa.puede_anular ? `<button data-annul="${escapeHtml(repa.id)}" type="button">Anular repa</button>` : ''}</article>`).join('') : '<p class="empty-copy">No existen repaletizajes para esta selección.</p>'; }
async function loadHistory() { const query = new URLSearchParams({ per_page: '50' }); if (el.historyFilter.value.trim()) query.set('folio', el.historyFilter.value.trim()); const response = await api(`/api/validacion/repaletizajes?${query}`); state.history = response.data || []; renderHistory(); }
async function annul(id) { const reason = prompt('Motivo de anulación (mínimo 5 caracteres):'); if (reason === null) return; if (reason.trim().length < 5) { toast('El motivo es demasiado breve.', true); return; } setBusy(true, 'Anulando repa…'); try { await api(`/api/validacion/repaletizajes/${id}/anular`, { method: 'POST', body: JSON.stringify({ operacion_id: uuid(), motivo: reason.trim() }) }); toast('Repaletizaje anulado y cantidades restauradas.'); await loadHistory(); } catch (error) { toast(error.message, true); } finally { setBusy(false); } }
el.login.addEventListener('submit', async (event) => { event.preventDefault(); el.loginError.textContent = ''; setBusy(true, 'Validando acceso…'); try { const data = new FormData(el.login); const payload = await api('/api/acceso-oficina', { method: 'POST', body: JSON.stringify({ email: data.get('email'), password: data.get('password') }) }); persist(payload); if (!showApp()) { clearSession(); throw new ApiError('Tu perfil no tiene acceso a repaletizajes.', 403); } await loadHistory(); } catch (error) { el.loginError.textContent = error.message; } finally { setBusy(false); } });
el.logout.addEventListener('click', async () => { try { await api('/api/acceso-oficina', { method: 'DELETE' }); } catch {} clearSession(); });
el.addSource.addEventListener('click', () => void addSource()); el.sourceInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); void addSource(); } });
el.form.addEventListener('change', render); el.form.addEventListener('input', (event) => { if (!event.target.matches('[data-contribution]')) { render(); return; } const source = state.sources.find((item) => item.id === event.target.dataset.contribution); if (source) source.aporte = Math.min(source.cantidad_cajas, Math.max(1, Number(event.target.value || 1))); render(); });
el.sourceList.addEventListener('click', (event) => { const remove = event.target.closest('[data-remove]'); if (remove) { state.sources = state.sources.filter((source) => source.id !== remove.dataset.remove); render(); } });
el.form.addEventListener('submit', (event) => { event.preventDefault(); void submit(); }); el.clear.addEventListener('click', reset); el.reload.addEventListener('click', () => void loadHistory()); el.historyFilter.addEventListener('change', () => void loadHistory()); el.history.addEventListener('click', (event) => { const button = event.target.closest('[data-annul]'); if (button) void annul(button.dataset.annul); });
render(); if (state.token && state.identity && showApp()) void loadHistory(); else clearSession();
''')

write('mobile/src/domain/repaletizaje.ts', r'''export type RepalletizingFolio = {
  existe: boolean;
  id: string;
  numero_folio: string;
  tipo_bulto: string;
  cantidad_cajas: number;
  activo: boolean;
  estado_operacional: string;
  condicion_termica: string;
  cliente: string | null;
  especie: string | null;
  marca: string | null;
  variedad: string | null;
  calibre: string | null;
  envase: string | null;
  categoria: string | null;
  csg: string | null;
  predio: string | null;
  cuartel: string | null;
};
export type RepalletizingOrigin = { folio_id: string; cantidad_aportada: number };
export type CreateRepalletizing = {
  operacion_id: string;
  tipo_resultado: 'pallet' | 'saldo';
  estrategia_folio: 'conservar' | 'nuevo';
  numero_folio_resultante: string;
  folio_conservado_id: string | null;
  cantidad_objetivo: number | null;
  origenes: RepalletizingOrigin[];
  observacion: string | null;
};
export type Repalletizing = {
  id: string;
  codigo: string;
  tipo_resultado: 'pallet' | 'saldo';
  estrategia_folio: 'conservar' | 'nuevo';
  cantidad_objetivo: number | null;
  cantidad_resultante: number;
  condicion_termica: string;
  estado: string;
  advertencias: Array<{ campo: string; mensaje: string }>;
  folio_resultante: { id: string; numero_folio: string; tipo_bulto: string; cantidad_cajas: number; estado_operacional: string; condicion_termica: string };
  origenes: Array<{ id: string; orden: number; cajas_antes: number; cajas_aportadas: number; cajas_despues: number; folio: { id: string; numero_folio: string } }>;
  confirmado_at: string;
};
''')

write('mobile/src/services/repaletizajeApi.ts', r'''import { CreateRepalletizing, Repalletizing, RepalletizingFolio } from '../domain/repaletizaje';

async function request<T>(baseUrl: string, token: string, path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${baseUrl}${path}`, { ...init, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(init.body ? { 'Content-Type': 'application/json' } : {}) } });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] as string || data.message || 'No fue posible completar la operación.');
  return data as T;
}
export async function findRepalletizingFolio(baseUrl: string, token: string, number: string): Promise<RepalletizingFolio> {
  return request(baseUrl, token, `/api/validacion/repaletizajes/folios/${encodeURIComponent(number)}`);
}
export async function createRepalletizing(baseUrl: string, token: string, payload: CreateRepalletizing): Promise<Repalletizing> {
  const response = await request<{ data: Repalletizing }>(baseUrl, token, '/api/validacion/repaletizajes', { method: 'POST', body: JSON.stringify(payload) });
  return response.data;
}
export async function listRepalletizings(baseUrl: string, token: string): Promise<Repalletizing[]> {
  const response = await request<{ data: Repalletizing[] }>(baseUrl, token, '/api/validacion/repaletizajes?per_page=20');
  return response.data;
}
''')

write('mobile/src/screens/RepalletizingScreen.tsx', r'''import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { AuthSession } from '../domain/estiba';
import { Repalletizing, RepalletizingFolio } from '../domain/repaletizaje';
import { createRepalletizing, findRepalletizingFolio, listRepalletizings } from '../services/repaletizajeApi';
import { colors } from '../theme/colors';

type Props = { auth: AuthSession; baseUrl: string; onLogout: () => void };
type Source = RepalletizingFolio & { aporte: string };
const hard = ['cliente', 'especie', 'marca', 'condicion_termica'] as const;
const mix = ['variedad', 'calibre', 'envase', 'categoria', 'csg', 'predio', 'cuartel'] as const;
export function RepalletizingScreen({ auth, baseUrl, onLogout }: Props) {
  const [type, setType] = useState<'pallet' | 'saldo'>('pallet');
  const [strategy, setStrategy] = useState<'conservar' | 'nuevo'>('conservar');
  const [capacity, setCapacity] = useState('120');
  const [resultNumber, setResultNumber] = useState('');
  const [keptId, setKeptId] = useState('');
  const [lookup, setLookup] = useState('');
  const [sources, setSources] = useState<Source[]>([]);
  const [history, setHistory] = useState<Repalletizing[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  useEffect(() => { void reload(); }, []);
  const total = useMemo(() => sources.reduce((sum, source) => sum + Number(source.aporte || 0), 0), [sources]);
  const mismatches = useMemo(() => hard.filter((field) => new Set(sources.map((source) => normalize(source[field]))).size > 1), [sources]);
  const mixes = useMemo(() => mix.filter((field) => new Set(sources.map((source) => normalize(source[field]))).size > 1), [sources]);
  const actualResult = strategy === 'conservar' ? sources.find((source) => source.id === keptId)?.numero_folio || '' : resultNumber.trim().toUpperCase();
  async function reload() { try { setHistory(await listRepalletizings(baseUrl, auth.token)); } catch (reason) { setError(messageOf(reason)); } }
  async function add() { const number = lookup.trim().toUpperCase(); if (!number) return; if (sources.some((source) => source.numero_folio === number)) { setError('Ese folio ya fue agregado.'); return; } setBusy(true); setError(''); try { const source = await findRepalletizingFolio(baseUrl, auth.token, number); if (!source.existe || !source.activo || source.tipo_bulto !== 'saldo' || source.cantidad_cajas < 1) throw new Error('El folio no es un saldo activo.'); if (!['pendiente_prefrio', 'prefrio_aprobado'].includes(source.condicion_termica)) throw new Error('El estado térmico no permite repaletizar.'); const remaining = Math.max(0, Number(capacity || 0) - total); const aporte = type === 'pallet' ? Math.min(source.cantidad_cajas, remaining || source.cantidad_cajas) : source.cantidad_cajas; setSources((current) => [...current, { ...source, aporte: String(aporte) }]); if (!keptId) setKeptId(source.id); setLookup(''); } catch (reason) { setError(messageOf(reason)); } finally { setBusy(false); } }
  function update(id: string, aporte: string) { setSources((current) => current.map((source) => source.id === id ? { ...source, aporte } : source)); }
  function remove(id: string) { setSources((current) => current.filter((source) => source.id !== id)); if (keptId === id) setKeptId(''); }
  async function submit() { setError(''); setMessage(''); const target = Number(capacity || 0); if (sources.length < 2) { setError('Agrega al menos dos saldos.'); return; } if (mismatches.length) { setError('Cliente, especie, marca y estado térmico deben coincidir.'); return; } if (!actualResult) { setError('Define el folio resultante.'); return; } if (type === 'pallet' && total !== target) { setError(`El pallet debe completar exactamente ${target} cajas.`); return; } if (type === 'saldo' && target && total >= target) { setError('El saldo debe quedar bajo la capacidad completa.'); return; } const kept = sources.find((source) => source.id === keptId); if (strategy === 'conservar' && (!kept || Number(kept.aporte) !== kept.cantidad_cajas)) { setError('El folio conservado debe aportar todas sus cajas.'); return; } setBusy(true); try { const result = await createRepalletizing(baseUrl, auth.token, { operacion_id: Crypto.randomUUID(), tipo_resultado: type, estrategia_folio: strategy, numero_folio_resultante: actualResult, folio_conservado_id: strategy === 'conservar' ? keptId : null, cantidad_objetivo: target || null, origenes: sources.map((source) => ({ folio_id: source.id, cantidad_aportada: Number(source.aporte) })), observacion: null }); setMessage(`${result.codigo}: ${result.folio_resultante.numero_folio} confirmado.`); setSources([]); setLookup(''); setResultNumber(''); setKeptId(''); await reload(); } catch (reason) { setError(messageOf(reason)); } finally { setBusy(false); } }
  return <View style={styles.screen}><View style={styles.header}><View><Text style={styles.eyebrow}>VALIDACIÓN · REPALETIZAJES</Text><Text style={styles.title}>Consolidar saldos</Text><Text style={styles.copy}>Escanea o escribe folios. Nunca mezcla cliente, especie, marca ni estado térmico.</Text></View><View style={styles.headerActions}><Pressable onPress={() => void reload()} style={styles.secondary}><Text style={styles.secondaryText}>↻</Text></Pressable><Pressable onPress={onLogout} style={styles.secondary}><Text style={styles.secondaryText}>Salir</Text></Pressable></View></View><ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled"><View style={styles.options}><Toggle active={type === 'pallet'} label="Pallet completo" onPress={() => setType('pallet')} /><Toggle active={type === 'saldo'} label="Saldo consolidado" onPress={() => setType('saldo')} /></View><View style={styles.options}><Toggle active={strategy === 'conservar'} label="Conservar folio" onPress={() => setStrategy('conservar')} /><Toggle active={strategy === 'nuevo'} label="Otro folio" onPress={() => setStrategy('nuevo')} /></View><Field label="Capacidad del pallet"><TextInput keyboardType="number-pad" onChangeText={setCapacity} style={styles.input} value={capacity} /></Field>{strategy === 'nuevo' ? <Field label="Folio resultante"><TextInput autoCapitalize="characters" onChangeText={setResultNumber} placeholder="Escanear o escribir" placeholderTextColor={colors.muted} style={styles.input} value={resultNumber} /></Field> : null}<View style={styles.addRow}><TextInput autoCapitalize="characters" onChangeText={setLookup} onSubmitEditing={() => void add()} placeholder="Escanear o escribir saldo" placeholderTextColor={colors.muted} style={[styles.input, styles.addInput]} value={lookup} /><Pressable disabled={busy} onPress={() => void add()} style={styles.primary}><Text style={styles.primaryText}>Agregar</Text></Pressable></View>{mismatches.length ? <Text style={styles.block}>BLOQUEADO: {mismatches.join(' · ')}</Text> : null}{mixes.length ? <View style={styles.warningRow}>{mixes.map((field) => <Text key={field} style={styles.warning}>⚠ MIX {field.toUpperCase()}</Text>)}</View> : null}{sources.map((source) => <View key={source.id} style={styles.source}><Pressable disabled={strategy !== 'conservar'} onPress={() => setKeptId(source.id)} style={[styles.keep, keptId === source.id && strategy === 'conservar' && styles.keepActive]}><Text style={styles.keepText}>{keptId === source.id && strategy === 'conservar' ? '✓ RESULTADO' : 'CONSERVAR'}</Text></Pressable><View style={styles.sourceInfo}><Text style={styles.sourceNumber}>{source.numero_folio}</Text><Text style={styles.sourceMeta}>{source.cantidad_cajas} cajas · {source.cliente} · {source.especie}</Text><Text style={styles.sourceMeta}>{source.calibre} · CSG {source.csg} · {source.condicion_termica}</Text></View><Field label="Aporta"><TextInput keyboardType="number-pad" onChangeText={(value) => update(source.id, value)} style={styles.smallInput} value={source.aporte} /></Field><Text style={styles.after}>Queda {Math.max(0, source.cantidad_cajas - Number(source.aporte || 0))}</Text><Pressable onPress={() => remove(source.id)}><Text style={styles.remove}>Quitar</Text></Pressable></View>)}<View style={styles.preview}><Text style={styles.previewLabel}>RESULTADO</Text><Text style={styles.previewNumber}>{actualResult || 'Sin definir'}</Text><Text style={styles.previewMeta}>{type === 'pallet' ? `${total}/${capacity || '—'} cajas` : `${total} cajas`} · {sources[0]?.condicion_termica || '—'}</Text></View>{message ? <Text style={styles.message}>{message}</Text> : null}{error ? <Text style={styles.error}>{error}</Text> : null}<Pressable disabled={busy} onPress={() => void submit()} style={[styles.primary, styles.confirm]}>{busy ? <ActivityIndicator color={colors.accentText} /> : <Text style={styles.primaryText}>Confirmar repaletizaje</Text>}</Pressable><Text style={styles.sectionTitle}>Repas recientes</Text>{history.map((repa) => <View key={repa.id} style={styles.history}><Text style={styles.sourceNumber}>{repa.codigo} · {repa.folio_resultante.numero_folio}</Text><Text style={styles.sourceMeta}>{repa.tipo_resultado} · {repa.cantidad_resultante} cajas · {repa.condicion_termica}</Text></View>)}</ScrollView></View>;
}
function normalize(value: unknown) { return String(value ?? '').trim().toUpperCase(); }
function messageOf(reason: unknown) { return reason instanceof Error ? reason.message : 'No fue posible completar la operación.'; }
function Toggle({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) { return <Pressable onPress={onPress} style={[styles.toggle, active && styles.toggleActive]}><Text style={[styles.toggleText, active && styles.toggleTextActive]}>{label}</Text></Pressable>; }
function Field({ label, children }: { label: string; children: React.ReactNode }) { return <View style={styles.field}><Text style={styles.fieldLabel}>{label}</Text>{children}</View>; }
const styles = StyleSheet.create({ screen: { flex: 1, backgroundColor: colors.background }, header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12, padding: 18, borderBottomWidth: 1, borderBottomColor: colors.border }, headerActions: { flexDirection: 'row', gap: 8 }, eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.2 }, title: { color: colors.text, fontSize: 26, fontWeight: '900', marginTop: 4 }, copy: { color: colors.muted, fontSize: 11, marginTop: 4 }, content: { padding: 16, gap: 12 }, options: { flexDirection: 'row', gap: 8 }, toggle: { flex: 1, minHeight: 44, alignItems: 'center', justifyContent: 'center', borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep }, toggleActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark }, toggleText: { color: colors.muted, fontWeight: '900' }, toggleTextActive: { color: colors.cyan }, field: { gap: 5 }, fieldLabel: { color: colors.muted, fontSize: 10, fontWeight: '900' }, input: { minHeight: 46, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel, color: colors.text, paddingHorizontal: 12 }, addRow: { flexDirection: 'row', alignItems: 'center', gap: 8 }, addInput: { flex: 1 }, primary: { minHeight: 46, alignItems: 'center', justifyContent: 'center', borderRadius: 10, backgroundColor: colors.cyan, paddingHorizontal: 18 }, primaryText: { color: colors.accentText, fontWeight: '900' }, secondary: { minHeight: 40, alignItems: 'center', justifyContent: 'center', borderRadius: 9, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 12 }, secondaryText: { color: colors.cyan, fontWeight: '900' }, source: { flexDirection: 'row', alignItems: 'center', gap: 9, borderWidth: 1, borderColor: colors.border, borderRadius: 12, backgroundColor: colors.panel, padding: 10 }, sourceInfo: { flex: 1 }, sourceNumber: { color: colors.text, fontWeight: '900' }, sourceMeta: { color: colors.muted, fontSize: 10, marginTop: 2 }, keep: { borderWidth: 1, borderColor: colors.border, borderRadius: 8, padding: 7 }, keepActive: { borderColor: colors.cyan, backgroundColor: colors.cyanDark }, keepText: { color: colors.cyan, fontSize: 9, fontWeight: '900' }, smallInput: { width: 72, minHeight: 40, borderWidth: 1, borderColor: colors.border, borderRadius: 8, backgroundColor: colors.backgroundDeep, color: colors.text, textAlign: 'center' }, after: { color: colors.muted, fontSize: 10 }, remove: { color: colors.red, fontWeight: '900', fontSize: 10 }, warningRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 }, warning: { color: '#f0c66d', borderWidth: 1, borderColor: '#725f34', borderRadius: 999, paddingHorizontal: 9, paddingVertical: 6, fontSize: 9, fontWeight: '900' }, block: { color: colors.red, fontWeight: '900', borderWidth: 1, borderColor: colors.red, borderRadius: 9, padding: 10 }, preview: { borderWidth: 1, borderColor: colors.cyanDark, borderRadius: 12, backgroundColor: colors.panel, padding: 14 }, previewLabel: { color: colors.muted, fontSize: 9, fontWeight: '900' }, previewNumber: { color: colors.cyan, fontSize: 22, fontWeight: '900', marginTop: 4 }, previewMeta: { color: colors.muted, marginTop: 3 }, confirm: { width: '100%' }, message: { color: colors.cyan, fontWeight: '800' }, error: { color: colors.red, fontWeight: '800' }, sectionTitle: { color: colors.text, fontSize: 18, fontWeight: '900', marginTop: 12 }, history: { borderWidth: 1, borderColor: colors.border, borderRadius: 10, backgroundColor: colors.panel, padding: 11 } });
''')

# Web visibility.
replace(
    'routes/web.php',
    "Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');\n",
    "Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');\nRoute::view('/oficina/validacion/repaletizajes', 'office.repalletizing');\n",
)
replace(
    'resources/views/components/office/navigation.blade.php',
    "            ['key' => 'catalogo-validacion', 'module' => 'frigorifico.catalogos', 'label' => 'Catálogos PT', 'href' => '/oficina/validacion/catalogo', 'permissions' => ['puede_consultar_catalogos_validacion']],\n",
    "            ['key' => 'repaletizajes', 'module' => 'frigorifico.validacion', 'label' => 'Repaletizajes', 'href' => '/oficina/validacion/repaletizajes', 'permissions' => ['puede_consultar_repaletizajes']],\n            ['key' => 'catalogo-validacion', 'module' => 'frigorifico.catalogos', 'label' => 'Catálogos PT', 'href' => '/oficina/validacion/catalogo', 'permissions' => ['puede_consultar_catalogos_validacion']],\n",
)

# Mobile module.
replace(
    'mobile/src/domain/estiba.ts',
    "  | 'validacion'\n  | 'validacion_mp'",
    "  | 'validacion'\n  | 'repaletizaje'\n  | 'validacion_mp'",
)
replace(
    'mobile/src/domain/estiba.ts',
    "  puede_validar_pallets: boolean;\n",
    "  puede_validar_pallets: boolean;\n  puede_operar_repaletizajes?: boolean;\n  puede_consultar_repaletizajes?: boolean;\n  puede_anular_repaletizajes?: boolean;\n",
)
replace(
    'mobile/App.tsx',
    "import { PrefrioWorkspaceScreen } from './src/screens/PrefrioWorkspaceScreen';\n",
    "import { PrefrioWorkspaceScreen } from './src/screens/PrefrioWorkspaceScreen';\nimport { RepalletizingScreen } from './src/screens/RepalletizingScreen';\n",
)
replace(
    'mobile/App.tsx',
    "    const orientation = activeModule === 'validacion' || activeModule === 'validacion_mp' || activeModule === 'fruta_proceso'",
    "    const orientation = activeModule === 'validacion' || activeModule === 'repaletizaje' || activeModule === 'validacion_mp' || activeModule === 'fruta_proceso'",
)
replace(
    'mobile/App.tsx',
    "            ) : activeModule === 'prefrio' ? (",
    "            ) : activeModule === 'repaletizaje' ? (\n              <RepalletizingScreen auth={auth} baseUrl={api.baseUrl ?? ''} onLogout={() => void logoutPersistentModule()} />\n            ) : activeModule === 'prefrio' ? (",
)
replace(
    'mobile/App.tsx',
    "    'validacion',\n    'validacion_mp',",
    "    'validacion',\n    'repaletizaje',\n    'validacion_mp',",
)
replace(
    'mobile/App.tsx',
    "  return module === 'validacion'\n    ? 'Validación'",
    "  return module === 'validacion'\n    ? 'Validación'\n    : module === 'repaletizaje'\n      ? 'Repaletizajes'",
)
replace(
    'mobile/App.tsx',
    "        {modules.includes('validacion_mp') ? (",
    "        {modules.includes('repaletizaje') ? (\n          <Pressable onPress={() => onSelect('repaletizaje')} style={styles.selectorCard}>\n            <Text style={styles.selectorIcon}>⇄</Text>\n            <Text style={styles.selectorCardTitle}>Repaletizajes</Text>\n            <Text style={styles.selectorCardCopy}>Consolidar saldos con genealogía y control térmico.</Text>\n          </Pressable>\n        ) : null}\n        {modules.includes('validacion_mp') ? (",
)
