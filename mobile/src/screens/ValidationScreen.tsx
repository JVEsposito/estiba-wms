import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  AppState,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';

import { AuthSession } from '../domain/estiba';
import {
  RegisterValidationPayload,
  ValidationAttempt,
  ValidationCatalog,
  ValidationOutboxItem,
  ValidationResult,
} from '../domain/validation';
import { ApiError } from '../services/apiError';
import {
  getValidationCatalog,
  listRecentValidations,
  registerValidation,
} from '../services/validationApi';
import {
  enqueueValidation,
  loadCachedValidationCatalog,
  loadValidationOutbox,
  markValidationOutboxItem,
  removeValidationFromOutbox,
  retryValidationOutboxItem,
  saveValidationCatalog,
} from '../services/validationOfflineStore';
import { colors } from '../theme/colors';

type ValidationScreenProps = {
  auth: AuthSession;
  baseUrl: string | null;
  onLogout: () => void;
};

type Option = { value: string; label: string; search?: string };

type ObservationDraft = {
  result: Exclude<ValidationResult, 'aprobado'>;
  reason: string;
  note: string;
};

export function ValidationScreen({ auth, baseUrl, onLogout }: ValidationScreenProps) {
  const { height, width } = useWindowDimensions();
  const compact = width < 700 || width < height;
  const folioInput = useRef<TextInput>(null);
  const flushing = useRef(false);
  const [catalog, setCatalog] = useState<ValidationCatalog | null>(null);
  const [outbox, setOutbox] = useState<ValidationOutboxItem[]>([]);
  const [recent, setRecent] = useState<ValidationAttempt[]>([]);
  const [busy, setBusy] = useState(true);
  const [online, setOnline] = useState(Boolean(baseUrl));
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const [lastSync, setLastSync] = useState<string | null>(null);
  const [folio, setFolio] = useState('');
  const [boxes, setBoxes] = useState('');
  const [packageType, setPackageType] = useState<'pallet' | 'saldo'>('pallet');
  const [species, setSpecies] = useState('');
  const [variety, setVariety] = useState('');
  const [caliber, setCaliber] = useState('');
  const [packageName, setPackageName] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [client, setClient] = useState('');
  const [brand, setBrand] = useState('');
  const [csg, setCsg] = useState('');
  const [observation, setObservation] = useState<ObservationDraft | null>(null);

  const userId = auth.usuario.id;
  const deviceId = auth.dispositivo.id;
  const canReject = auth.usuario.capacidades.puede_rechazar_pallets;

  const activeArticles = useMemo(
    () => catalog?.articulos.filter((item) => item.activo) ?? [],
    [catalog],
  );
  const categoryOptions = useMemo(
    () => (catalog?.categorias ?? [])
      .filter((item) => item.activo)
      .map((item) => ({ value: item.id, label: item.nombre, search: item.codigo_externo ?? '' })),
    [catalog],
  );
  const selectedCategory = useMemo(
    () => catalog?.categorias.find((item) => item.id === categoryId && item.activo) ?? null,
    [catalog, categoryId],
  );
  const speciesOptions = useMemo(
    () => uniqueOptions(activeArticles.map((item) => item.especie)),
    [activeArticles],
  );
  const varietyOptions = useMemo(
    () => uniqueOptions(activeArticles.filter((item) => !species || item.especie === species).map((item) => item.variedad)),
    [activeArticles, species],
  );
  const caliberOptions = useMemo(
    () => uniqueOptions(activeArticles.filter((item) => (!species || item.especie === species) && (!variety || item.variedad === variety)).map((item) => item.calibre)),
    [activeArticles, species, variety],
  );
  const packageOptions = useMemo(
    () => uniqueOptions(activeArticles.filter((item) => (!species || item.especie === species) && (!variety || item.variedad === variety) && (!caliber || item.calibre === caliber)).map((item) => item.envase)),
    [activeArticles, species, variety, caliber],
  );
  const selectedArticle = useMemo(
    () => activeArticles.find((item) => item.especie === species && item.variedad === variety && item.calibre === caliber && item.envase === packageName) ?? null,
    [activeArticles, species, variety, caliber, packageName],
  );

  const eligibleOrigins = useMemo(() => {
    if (!catalog) return [];
    const combinations = selectedArticle
      ? catalog.combinaciones.filter((item) => item.articulo_validacion_id === selectedArticle.id)
      : catalog.combinaciones;
    const ids = new Set(combinations.map((item) => item.origen_validacion_id));
    return catalog.origenes.filter((item) => item.activo && ids.has(item.id));
  }, [catalog, selectedArticle]);
  const clientOptions = useMemo(
    () => uniqueOptions(eligibleOrigins.map((item) => item.cliente)),
    [eligibleOrigins],
  );
  const brandOptions = useMemo(
    () => uniqueOptions(eligibleOrigins.filter((item) => !client || item.cliente === client).map((item) => item.marca)),
    [eligibleOrigins, client],
  );
  const csgOptions = useMemo(
    () => eligibleOrigins
      .filter((item) => (!client || item.cliente === client) && (!brand || item.marca === brand))
      .map((item) => ({
        value: item.csg,
        label: `${item.csg}${item.predio ? ` — ${item.predio}` : ''}`,
        search: `${item.csg} ${item.predio ?? ''}`,
      })),
    [eligibleOrigins, client, brand],
  );
  const selectedOrigin = useMemo(
    () => eligibleOrigins.find((item) => item.cliente === client && item.marca === brand && item.csg === csg) ?? null,
    [eligibleOrigins, client, brand, csg],
  );
  const selectedCombination = useMemo(
    () => catalog?.combinaciones.find((item) => item.articulo_validacion_id === selectedArticle?.id && item.origen_validacion_id === selectedOrigin?.id) ?? null,
    [catalog, selectedArticle, selectedOrigin],
  );

  useEffect(() => {
    void initialize();
  }, []);

  useEffect(() => {
    const timer = setInterval(() => void flushOutbox(), 30000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void synchronize();
    });
    return () => { clearInterval(timer); subscription.remove(); };
  }, [baseUrl, auth.token]);

  async function initialize() {
    setBusy(true);
    setError('');
    try {
      const [cached, queued] = await Promise.all([
        loadCachedValidationCatalog(userId, deviceId),
        loadValidationOutbox(userId, deviceId),
      ]);
      if (cached) setCatalog(cached);
      setOutbox(queued);
      await synchronize();
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
      setTimeout(() => folioInput.current?.focus(), 250);
    }
  }

  async function synchronize() {
    if (!baseUrl) {
      setOnline(false);
      return;
    }

    try {
      const loaded = await getValidationCatalog(baseUrl, auth.token);
      await saveValidationCatalog(userId, deviceId, loaded);
      setCatalog(loaded);
      setRecent(await listRecentValidations(baseUrl, auth.token));
      setOnline(true);
      setLastSync(new Date().toISOString());
      await flushOutbox();
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 401) {
        Alert.alert('Sesión vencida', 'Vuelve a iniciar el turno.');
        onLogout();
        return;
      }
      setOnline(!(reason instanceof ApiError && reason.status === 0));
      if (!catalog) setError(messageFrom(reason));
    }
  }

  async function flushOutbox() {
    if (!baseUrl || flushing.current) return;
    flushing.current = true;
    try {
      let items = await loadValidationOutbox(userId, deviceId);
      for (const item of items.filter((candidate) => candidate.status === 'pendiente')) {
        try {
          await registerValidation(baseUrl, auth.token, item.payload);
          items = await removeValidationFromOutbox(userId, deviceId, item.id);
          setOnline(true);
        } catch (reason) {
          if (reason instanceof ApiError && reason.status === 0) {
            setOnline(false);
            break;
          }
          if (reason instanceof ApiError && reason.status === 409) {
            items = await markValidationOutboxItem(userId, deviceId, item.id, 'conflicto', reason.message);
            continue;
          }
          items = await markValidationOutboxItem(userId, deviceId, item.id, 'error', messageFrom(reason));
        }
      }
      setOutbox(items);
      if (baseUrl && online) {
        try { setRecent(await listRecentValidations(baseUrl, auth.token)); } catch { /* conserva historial visible */ }
      }
    } finally {
      flushing.current = false;
    }
  }

  function clearOrigin() {
    setClient(''); setBrand(''); setCsg('');
  }

  function validateForm() {
    if (!catalog) return 'No existe un catálogo sincronizado en esta PDA.';
    if (!folio.trim()) return 'Escanea o ingresa el folio.';
    if (!Number.isInteger(Number(boxes)) || Number(boxes) < 1) return 'Ingresa una cantidad válida de cajas.';
    if (!selectedCategory) return 'Selecciona una categoría.';
    if (!selectedArticle) return 'Completa especie, variedad, calibre y envase.';
    if (!selectedOrigin) return 'Completa cliente, marca y CSG.';
    if (!selectedCombination) return 'La combinación artículo–origen no está habilitada.';
    return null;
  }

  async function submit(result: ValidationResult, reason?: string, note?: string) {
    const problem = validateForm();
    if (problem) {
      setError(problem);
      return;
    }
    if (!catalog || !selectedArticle || !selectedOrigin || !selectedCategory) return;

    const payload: RegisterValidationPayload = {
      operacion_id: Crypto.randomUUID(),
      numero_folio: folio.trim().toUpperCase(),
      tipo_bulto: packageType,
      cantidad_cajas: Number(boxes),
      temporada_id: catalog.temporada.id,
      catalogo_version: catalog.temporada.version_catalogo,
      articulo_validacion_id: selectedArticle.id,
      origen_validacion_id: selectedOrigin.id,
      categoria_validacion_id: selectedCategory.id,
      resultado: result,
      ...(reason ? { motivo: reason } : {}),
      ...(note?.trim() ? { observacion: note.trim() } : {}),
      generado_dispositivo_at: new Date().toISOString(),
    };

    setBusy(true);
    setError('');
    setNotice('');
    try {
      let items = await enqueueValidation(userId, deviceId, payload);
      setOutbox(items);
      if (!baseUrl) {
        setNotice(`${payload.numero_folio} quedó en la bandeja de salida.`);
      } else {
        try {
          const attempt = await registerValidation(baseUrl, auth.token, payload);
          items = await removeValidationFromOutbox(userId, deviceId, payload.operacion_id);
          setOutbox(items);
          setRecent((current) => [attempt, ...current.filter((item) => item.id !== attempt.id)].slice(0, 10));
          setOnline(true);
          setNotice(result === 'aprobado'
            ? `${payload.numero_folio} aprobado y creado como pendiente de prefrío.`
            : `${payload.numero_folio} registrado como ${result}.`);
        } catch (reasonCaught) {
          if (reasonCaught instanceof ApiError && reasonCaught.status === 0) {
            setOnline(false);
            setNotice(`${payload.numero_folio} quedó guardado localmente para envío automático.`);
          } else if (reasonCaught instanceof ApiError && reasonCaught.status === 409) {
            items = await markValidationOutboxItem(userId, deviceId, payload.operacion_id, 'conflicto', reasonCaught.message);
            setOutbox(items);
            setError(`Conflicto en ${payload.numero_folio}: ${reasonCaught.message}`);
          } else {
            items = await markValidationOutboxItem(userId, deviceId, payload.operacion_id, 'error', messageFrom(reasonCaught));
            setOutbox(items);
            setError(messageFrom(reasonCaught));
          }
        }
      }

      setFolio('');
      setBoxes('');
      setObservation(null);
      setTimeout(() => folioInput.current?.focus(), 180);
    } finally {
      setBusy(false);
    }
  }

  async function retryItem(item: ValidationOutboxItem) {
    setOutbox(await retryValidationOutboxItem(userId, deviceId, item.id));
    await flushOutbox();
  }

  function logout() {
    if (outbox.some((item) => item.status === 'pendiente')) {
      Alert.alert(
        'Validaciones pendientes',
        'La bandeja permanece guardada en esta PDA. ¿Deseas cerrar la sesión igualmente?',
        [
          { text: 'Volver', style: 'cancel' },
          { text: 'Cerrar sesión', style: 'destructive', onPress: onLogout },
        ],
      );
      return;
    }
    onLogout();
  }

  if (!catalog && busy) {
    return <View style={styles.boot}><ActivityIndicator color={colors.cyan} size="large" /><Text style={styles.muted}>Preparando catálogo de validación…</Text></View>;
  }

  return (
    <View style={styles.screen}>
      <ScrollView contentContainerStyle={[styles.page, compact && styles.pageCompact]} keyboardShouldPersistTaps="handled">
        <View style={[styles.topbar, compact && styles.topbarCompact]}>
          <View><Text style={styles.eyebrow}>ESTIBA WMS · TERRENO</Text><Text style={[styles.title, compact && styles.titleCompact]}>Validación de pallets</Text></View>
          <View style={[styles.topbarRight, compact && styles.topbarRightCompact]}>
            <View style={[styles.connection, online ? styles.connectionOnline : styles.connectionOffline]}><Text style={styles.connectionText}>{online ? 'API conectada' : 'Modo desconectado'}</Text></View>
            <Pressable onPress={logout} style={styles.logout}><Text style={styles.logoutText}>Salir</Text></Pressable>
          </View>
        </View>

        <View style={[styles.statusStrip, compact && styles.statusStripCompact]}>
          <Text style={styles.statusText}>{catalog ? `${catalog.temporada.nombre} · catálogo v${catalog.temporada.version_catalogo}` : 'Sin catálogo'}</Text>
          <Text style={styles.statusText}>{outbox.filter((item) => item.status === 'pendiente').length} pendientes · {lastSync ? `última sincronización ${formatTime(lastSync)}` : 'sin sincronización reciente'}</Text>
        </View>

        {error ? <Pressable onPress={() => setError('')} style={styles.errorBanner}><Text style={styles.errorBannerText}>{error}</Text><Text style={styles.close}>×</Text></Pressable> : null}
        {notice ? <Pressable onPress={() => setNotice('')} style={styles.noticeBanner}><Text style={styles.noticeBannerText}>{notice}</Text><Text style={styles.close}>×</Text></Pressable> : null}

        <View style={[styles.mainGrid, compact && styles.mainGridCompact]}>
          <View style={[styles.formPanel, compact && styles.panelCompact]}>
            <Text style={styles.sectionEyebrow}>CAPTURA RÁPIDA</Text>
            <Text style={styles.sectionTitle}>Escanea y valida</Text>

            <Text style={styles.label}>Folio *</Text>
            <TextInput
              ref={folioInput}
              autoCapitalize="characters"
              autoCorrect={false}
              onChangeText={setFolio}
              placeholder="Escanear código de barras"
              placeholderTextColor={colors.muted}
              returnKeyType="next"
              selectTextOnFocus
              style={styles.folioInput}
              value={folio}
            />

            <View style={[styles.typeRow, compact && styles.typeRowCompact]}>
              {(['pallet', 'saldo'] as const).map((type) => (
                <Pressable key={type} onPress={() => setPackageType(type)} style={[styles.typeButton, compact && styles.typeButtonCompact, packageType === type && styles.typeButtonActive]}>
                  <Text style={[styles.typeButtonText, packageType === type && styles.typeButtonTextActive]}>{type === 'pallet' ? 'PALLET COMPLETO' : 'SALDO'}</Text>
                </Pressable>
              ))}
              <View style={[styles.boxField, compact && styles.boxFieldCompact]}><Text style={styles.label}>Cajas *</Text><TextInput keyboardType="number-pad" onChangeText={(value) => setBoxes(value.replace(/[^0-9]/g, ''))} placeholder="0" placeholderTextColor={colors.muted} style={styles.boxInput} value={boxes} /></View>
            </View>

            <Text style={styles.groupTitle}>Categoría</Text>
            <View style={[styles.fieldGrid, compact && styles.fieldGridCompact]}>
              <View style={styles.wideField}><SelectField compact={compact} label="Categoría" options={categoryOptions} searchable value={categoryId} onChange={setCategoryId} /></View>
            </View>

            <Text style={styles.groupTitle}>Artículo</Text>
            <View style={[styles.fieldGrid, compact && styles.fieldGridCompact]}>
              <SelectField compact={compact} label="Especie" options={speciesOptions} value={species} onChange={(value) => { setSpecies(value); setVariety(''); setCaliber(''); setPackageName(''); clearOrigin(); }} />
              <SelectField compact={compact} disabled={!species} label="Variedad" options={varietyOptions} value={variety} onChange={(value) => { setVariety(value); setCaliber(''); setPackageName(''); clearOrigin(); }} />
              <SelectField compact={compact} disabled={!variety} label="Calibre" options={caliberOptions} value={caliber} onChange={(value) => { setCaliber(value); setPackageName(''); clearOrigin(); }} />
              <SelectField compact={compact} disabled={!caliber} label="Envase" options={packageOptions} value={packageName} onChange={(value) => { setPackageName(value); clearOrigin(); }} />
            </View>

            <Text style={styles.groupTitle}>Origen comercial</Text>
            <View style={[styles.fieldGrid, compact && styles.fieldGridCompact]}>
              <SelectField compact={compact} disabled={!selectedArticle} label="Cliente" options={clientOptions} value={client} onChange={(value) => { setClient(value); setBrand(''); setCsg(''); }} />
              <SelectField compact={compact} disabled={!client} label="Marca" options={brandOptions} value={brand} onChange={(value) => { setBrand(value); setCsg(''); }} />
              <View style={styles.wideField}><SelectField compact={compact} disabled={!brand} label="CSG / Predio" options={csgOptions} searchable value={csg} onChange={setCsg} /></View>
            </View>

            <View style={styles.selectionSummary}>
              <Text style={styles.selectionSummaryTitle}>{selectedCombination && selectedCategory ? 'Combinación habilitada' : 'Completa los datos obligatorios'}</Text>
              <Text style={styles.selectionSummaryText}>{selectedCategory ? `Categoría: ${selectedCategory.nombre}` : 'Categoría pendiente'}</Text>
              <Text style={styles.selectionSummaryText}>{selectedArticle ? `${selectedArticle.especie} · ${selectedArticle.variedad} · ${selectedArticle.calibre} · ${selectedArticle.envase}` : 'Artículo pendiente'}</Text>
              <Text style={styles.selectionSummaryText}>{selectedOrigin ? `${selectedOrigin.cliente} · ${selectedOrigin.marca} · CSG ${selectedOrigin.csg}` : 'Origen pendiente'}</Text>
            </View>

            <View style={[styles.resultActions, compact && styles.resultActionsCompact]}>
              <Pressable disabled={busy} onPress={() => void submit('aprobado')} style={[styles.resultButton, compact && styles.resultButtonCompact, styles.approveButton]}><Text style={styles.resultIcon}>✓</Text><Text style={styles.resultButtonText}>APROBAR</Text></Pressable>
              <Pressable disabled={busy} onPress={() => setObservation({ result: 'observado', reason: '', note: '' })} style={[styles.resultButton, compact && styles.resultButtonCompact, styles.observeButton]}><Text style={styles.resultIcon}>!</Text><Text style={styles.resultButtonText}>OBSERVAR</Text></Pressable>
              {canReject ? <Pressable disabled={busy} onPress={() => setObservation({ result: 'rechazado', reason: '', note: '' })} style={[styles.resultButton, compact && styles.resultButtonCompact, styles.rejectButton]}><Text style={styles.resultIcon}>×</Text><Text style={styles.resultButtonText}>RECHAZAR</Text></Pressable> : null}
            </View>
          </View>

          <View style={[styles.sidePanel, compact && styles.panelCompact]}>
            <View style={styles.sideHeader}><View><Text style={styles.sectionEyebrow}>BANDEJA LOCAL</Text><Text style={styles.sideTitle}>{outbox.length} operaciones</Text></View><Pressable onPress={() => void synchronize()} style={styles.syncButton}><Text style={styles.syncButtonText}>↻ Sincronizar</Text></Pressable></View>
            {outbox.length ? outbox.map((item) => <View key={item.id} style={styles.queueItem}><View style={styles.queueContent}><Text style={styles.queueFolio}>{item.payload.numero_folio}</Text><Text style={styles.queueDetail}>{statusLabel(item.status)} · {item.payload.resultado} · {item.attempts} intentos</Text>{item.message ? <Text style={styles.queueError}>{item.message}</Text> : null}</View>{item.status !== 'pendiente' ? <Pressable onPress={() => void retryItem(item)} style={styles.retryButton}><Text style={styles.retryText}>Reintentar</Text></Pressable> : null}</View>) : <Text style={styles.empty}>No existen validaciones pendientes.</Text>}

            <Text style={[styles.sectionEyebrow, styles.recentEyebrow]}>ÚLTIMAS CONFIRMADAS</Text>
            {recent.slice(0, 6).map((item) => <View key={item.id} style={styles.recentItem}><View style={styles.queueContent}><Text style={styles.queueFolio}>{item.numero_folio}</Text><Text style={styles.queueDetail}>Intento {item.numero_intento} · {formatTime(item.recibido_servidor_at)}</Text></View><Text style={[styles.resultBadge, item.resultado === 'aprobado' ? styles.badgeApproved : item.resultado === 'observado' ? styles.badgeObserved : styles.badgeRejected]}>{item.estado === 'conflicto' ? 'conflicto' : item.resultado}</Text></View>)}
          </View>
        </View>
      </ScrollView>

      {busy ? <View pointerEvents="none" style={styles.busy}><ActivityIndicator color={colors.cyan} size="large" /><Text style={styles.busyText}>Procesando…</Text></View> : null}
      <ObservationModal catalog={catalog} draft={observation} onCancel={() => setObservation(null)} onConfirm={(draft) => void submit(draft.result, draft.reason, draft.note)} />
    </View>
  );
}

function SelectField({ label, options, value, onChange, compact = false, disabled = false, searchable = false }: { label: string; options: Option[]; value: string; onChange: (value: string) => void; compact?: boolean; disabled?: boolean; searchable?: boolean }) {
  const [visible, setVisible] = useState(false);
  const [query, setQuery] = useState('');
  const selected = options.find((option) => option.value === value);
  const filtered = options.filter((option) => `${option.label} ${option.search ?? ''}`.toLowerCase().includes(query.trim().toLowerCase()));

  return <>
    <View style={[styles.selectField, compact && styles.selectFieldCompact]}><Text style={styles.label}>{label} *</Text><Pressable disabled={disabled} onPress={() => setVisible(true)} style={[styles.selectButton, disabled && styles.disabled]}><Text numberOfLines={1} style={[styles.selectText, !selected && styles.placeholder]}>{selected?.label ?? 'Seleccionar'}</Text><Text style={styles.chevron}>⌄</Text></Pressable></View>
    <Modal animationType="fade" transparent visible={visible} onRequestClose={() => setVisible(false)}>
      <View style={styles.modalBackdrop}><View style={styles.selectorModal}><View style={styles.modalHeader}><Text style={styles.modalTitle}>{label}</Text><Pressable onPress={() => setVisible(false)}><Text style={styles.modalClose}>×</Text></Pressable></View>{searchable || options.length > 8 ? <TextInput autoFocus onChangeText={setQuery} placeholder={`Buscar ${label.toLowerCase()}`} placeholderTextColor={colors.muted} style={styles.searchInput} value={query} /> : null}<ScrollView keyboardShouldPersistTaps="handled" style={styles.optionList}>{filtered.map((option) => <Pressable key={option.value} onPress={() => { onChange(option.value); setQuery(''); setVisible(false); }} style={[styles.option, option.value === value && styles.optionSelected]}><Text style={styles.optionText}>{option.label}</Text></Pressable>)}{!filtered.length ? <Text style={styles.empty}>Sin opciones coincidentes.</Text> : null}</ScrollView></View></View>
    </Modal>
  </>;
}

function ObservationModal({ catalog, draft, onCancel, onConfirm }: { catalog: ValidationCatalog | null; draft: ObservationDraft | null; onCancel: () => void; onConfirm: (draft: ObservationDraft) => void }) {
  const { height, width } = useWindowDimensions();
  const compact = width < 700 || width < height;
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');
  useEffect(() => { setReason(draft?.reason ?? ''); setNote(draft?.note ?? ''); }, [draft]);
  if (!draft) return null;
  const reasonOptions = (catalog?.motivos ?? []).map((item) => ({ value: item, label: reasonLabel(item) }));
  const invalid = !reason || (reason === 'otro' && !note.trim());

  return <Modal animationType="slide" transparent visible onRequestClose={onCancel}><View style={[styles.modalBackdrop, compact && styles.modalBackdropCompact]}><View style={[styles.observationModal, compact && styles.observationModalCompact]}><ScrollView contentContainerStyle={styles.observationContent} keyboardShouldPersistTaps="handled"><View style={styles.modalHeader}><View><Text style={styles.sectionEyebrow}>{draft.result === 'rechazado' ? 'RECHAZO DEFINITIVO' : 'PALLET OBSERVADO'}</Text><Text style={styles.modalTitle}>Registra el motivo</Text></View><Pressable onPress={onCancel}><Text style={styles.modalClose}>×</Text></Pressable></View><SelectField compact={compact} label="Motivo" options={reasonOptions} searchable value={reason} onChange={setReason} /><Text style={styles.label}>Observación {reason === 'otro' ? '*' : ''}</Text><TextInput multiline onChangeText={setNote} placeholder="Describe el problema detectado o la corrección requerida" placeholderTextColor={colors.muted} style={styles.noteInput} value={note} /><View style={[styles.modalActions, compact && styles.modalActionsCompact]}><Pressable onPress={onCancel} style={[styles.cancelButton, compact && styles.modalButtonCompact]}><Text style={styles.cancelText}>Cancelar</Text></Pressable><Pressable disabled={invalid} onPress={() => onConfirm({ ...draft, reason, note })} style={[styles.confirmObservation, compact && styles.modalButtonCompact, draft.result === 'rechazado' && styles.rejectButton, invalid && styles.disabled]}><Text style={styles.resultButtonText}>Confirmar {draft.result}</Text></Pressable></View></ScrollView></View></View></Modal>;
}

function uniqueOptions(values: string[]): Option[] {
  return [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es')).map((value) => ({ value, label: value }));
}
function formatTime(value: string) { const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('es-CL', { hour: '2-digit', minute: '2-digit' }).format(date); }
function messageFrom(reason: unknown) { return reason instanceof Error ? reason.message : 'Ocurrió un error inesperado.'; }
function statusLabel(status: ValidationOutboxItem['status']) { return status === 'pendiente' ? 'Pendiente' : status === 'conflicto' ? 'Conflicto' : 'Requiere revisión'; }
function reasonLabel(value: string) { return value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  page: { padding: 18, gap: 14, paddingBottom: 46 },
  pageCompact: { padding: 10, gap: 10, paddingBottom: 32 },
  boot: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, backgroundColor: colors.background },
  muted: { color: colors.muted, fontWeight: '700' },
  topbar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: 16, borderWidth: 1, borderColor: colors.border, borderRadius: 16, backgroundColor: colors.panel },
  topbarCompact: { flexDirection: 'column', alignItems: 'stretch', padding: 13 },
  topbarRight: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  topbarRightCompact: { justifyContent: 'space-between' },
  eyebrow: { color: colors.cyan, fontSize: 11, fontWeight: '900', letterSpacing: 1.3 },
  title: { color: colors.text, fontSize: 23, fontWeight: '900', marginTop: 3 },
  titleCompact: { fontSize: 20 },
  connection: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 999, borderWidth: 1 },
  connectionOnline: { borderColor: colors.green, backgroundColor: colors.greenDark },
  connectionOffline: { borderColor: colors.red, backgroundColor: colors.blocked },
  connectionText: { color: colors.text, fontSize: 11, fontWeight: '900' },
  logout: { paddingHorizontal: 13, paddingVertical: 9, borderRadius: 10, borderWidth: 1, borderColor: colors.border },
  logoutText: { color: colors.muted, fontWeight: '800' },
  statusStrip: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', paddingHorizontal: 8 },
  statusStripCompact: { flexDirection: 'column', gap: 4, paddingHorizontal: 4 },
  statusText: { color: colors.muted, fontSize: 11, fontWeight: '800' },
  errorBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, padding: 13, borderRadius: 12, borderWidth: 1, borderColor: colors.red, backgroundColor: colors.blocked },
  errorBannerText: { flex: 1, color: colors.text, fontWeight: '800' },
  noticeBanner: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, padding: 13, borderRadius: 12, borderWidth: 1, borderColor: colors.green, backgroundColor: colors.greenDark },
  noticeBannerText: { flex: 1, color: colors.text, fontWeight: '800' },
  close: { color: colors.text, fontSize: 20, fontWeight: '900' },
  mainGrid: { flexDirection: 'row', alignItems: 'flex-start', gap: 16 },
  mainGridCompact: { flexDirection: 'column', gap: 10 },
  formPanel: { flex: 1.65, padding: 20, borderRadius: 17, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  sidePanel: { flex: 1, padding: 18, borderRadius: 17, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  panelCompact: { width: '100%', flexGrow: 0, flexBasis: 'auto', padding: 14 },
  sectionEyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.2 },
  sectionTitle: { color: colors.text, fontSize: 22, fontWeight: '900', marginTop: 4, marginBottom: 17 },
  label: { color: colors.muted, fontSize: 10, fontWeight: '900', letterSpacing: .7, textTransform: 'uppercase', marginBottom: 6 },
  folioInput: { minHeight: 60, paddingHorizontal: 16, borderRadius: 13, borderWidth: 2, borderColor: colors.cyan, color: colors.text, backgroundColor: colors.backgroundDeep, fontSize: 23, fontWeight: '900', letterSpacing: 1.2 },
  typeRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 9, marginTop: 12 },
  typeRowCompact: { flexWrap: 'wrap' },
  typeButton: { minHeight: 49, justifyContent: 'center', paddingHorizontal: 14, borderRadius: 11, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  typeButtonCompact: { flexGrow: 1, flexBasis: '46%', alignItems: 'center', paddingHorizontal: 8 },
  typeButtonActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  typeButtonText: { color: colors.muted, fontSize: 11, fontWeight: '900' },
  typeButtonTextActive: { color: colors.cyan },
  boxField: { flex: 1, minWidth: 110 },
  boxFieldCompact: { flexBasis: '100%' },
  boxInput: { minHeight: 49, paddingHorizontal: 13, borderRadius: 11, borderWidth: 1, borderColor: colors.border, color: colors.text, backgroundColor: colors.backgroundDeep, fontSize: 18, fontWeight: '900' },
  groupTitle: { color: colors.text, fontSize: 15, fontWeight: '900', marginTop: 20, marginBottom: 9 },
  fieldGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9 },
  fieldGridCompact: { flexDirection: 'column', flexWrap: 'nowrap' },
  selectField: { flex: 1, minWidth: 145 },
  selectFieldCompact: { width: '100%', flexGrow: 0, flexBasis: 'auto' },
  wideField: { flexBasis: '100%' },
  selectButton: { minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, paddingHorizontal: 12, borderRadius: 11, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  selectText: { flex: 1, color: colors.text, fontWeight: '800' },
  placeholder: { color: colors.muted },
  chevron: { color: colors.cyan, fontSize: 19, fontWeight: '900' },
  disabled: { opacity: .38 },
  selectionSummary: { marginTop: 17, padding: 13, borderRadius: 12, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.selected },
  selectionSummaryTitle: { color: colors.cyan, fontWeight: '900' },
  selectionSummaryText: { color: colors.text, fontSize: 12, marginTop: 4 },
  resultActions: { flexDirection: 'row', gap: 10, marginTop: 18 },
  resultActionsCompact: { flexDirection: 'column' },
  resultButton: { flex: 1, minHeight: 68, alignItems: 'center', justifyContent: 'center', borderRadius: 13, borderWidth: 1 },
  resultButtonCompact: { width: '100%', flexGrow: 0, flexBasis: 'auto', minHeight: 58, flexDirection: 'row', gap: 9 },
  approveButton: { borderColor: colors.green, backgroundColor: colors.greenDark },
  observeButton: { borderColor: colors.amber, backgroundColor: colors.amberDark },
  rejectButton: { borderColor: colors.red, backgroundColor: colors.blocked },
  resultIcon: { color: colors.text, fontSize: 21, fontWeight: '900' },
  resultButtonText: { color: colors.text, fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  sideHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 13 },
  sideTitle: { color: colors.text, fontSize: 18, fontWeight: '900', marginTop: 3 },
  syncButton: { paddingHorizontal: 11, paddingVertical: 8, borderRadius: 9, borderWidth: 1, borderColor: colors.cyanDark },
  syncButtonText: { color: colors.cyan, fontSize: 11, fontWeight: '900' },
  queueItem: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  queueContent: { flex: 1 },
  queueFolio: { color: colors.text, fontSize: 14, fontWeight: '900' },
  queueDetail: { color: colors.muted, fontSize: 10, marginTop: 3 },
  queueError: { maxWidth: 260, color: colors.red, fontSize: 10, marginTop: 4 },
  retryButton: { paddingHorizontal: 9, paddingVertical: 7, borderRadius: 8, borderWidth: 1, borderColor: colors.amber },
  retryText: { color: colors.amber, fontSize: 10, fontWeight: '900' },
  empty: { color: colors.muted, paddingVertical: 18, textAlign: 'center' },
  recentEyebrow: { marginTop: 25, marginBottom: 8 },
  recentItem: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  resultBadge: { paddingHorizontal: 8, paddingVertical: 5, borderRadius: 999, overflow: 'hidden', color: colors.text, fontSize: 9, fontWeight: '900', textTransform: 'uppercase' },
  badgeApproved: { backgroundColor: colors.greenDark },
  badgeObserved: { backgroundColor: colors.amberDark },
  badgeRejected: { backgroundColor: colors.blocked },
  busy: { ...StyleSheet.absoluteFill, alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: 'rgba(8,12,16,.72)' },
  busyText: { color: colors.text, fontWeight: '900' },
  modalBackdrop: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20, backgroundColor: 'rgba(0,0,0,.72)' },
  modalBackdropCompact: { padding: 10 },
  selectorModal: { width: '100%', maxWidth: 620, maxHeight: '78%', padding: 18, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panelStrong },
  observationModal: { width: '100%', maxWidth: 680, padding: 20, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panelStrong },
  observationModalCompact: { maxHeight: '94%', padding: 14 },
  observationContent: { flexGrow: 1 },
  modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 13 },
  modalTitle: { color: colors.text, fontSize: 20, fontWeight: '900' },
  modalClose: { color: colors.text, fontSize: 28, fontWeight: '900', paddingHorizontal: 7 },
  searchInput: { minHeight: 46, paddingHorizontal: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.border, color: colors.text, backgroundColor: colors.backgroundDeep, marginBottom: 10 },
  optionList: { maxHeight: 420 },
  option: { padding: 14, borderBottomWidth: 1, borderBottomColor: colors.borderSoft },
  optionSelected: { backgroundColor: colors.selected },
  optionText: { color: colors.text, fontWeight: '800' },
  noteInput: { minHeight: 110, padding: 13, borderRadius: 11, borderWidth: 1, borderColor: colors.border, color: colors.text, backgroundColor: colors.backgroundDeep, textAlignVertical: 'top' },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 16 },
  modalActionsCompact: { flexDirection: 'column-reverse' },
  modalButtonCompact: { width: '100%', alignItems: 'center' },
  cancelButton: { paddingHorizontal: 17, paddingVertical: 13, borderRadius: 10, borderWidth: 1, borderColor: colors.border },
  cancelText: { color: colors.muted, fontWeight: '900' },
  confirmObservation: { paddingHorizontal: 18, paddingVertical: 13, borderRadius: 10, borderWidth: 1, borderColor: colors.amber, backgroundColor: colors.amberDark },
});
