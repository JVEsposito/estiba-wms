import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { AuthSession } from '../domain/estiba';
import {
  PrefrioFolioCandidate,
  PrefrioMobileCache,
  PrefrioProcess,
  PrefrioTunnelPosition,
} from '../domain/prefrio';
import { ApiError } from '../services/apiError';
import {
  executePrefrioCommand,
  listEligiblePrefrioFolios,
  listPrefrioProcesses,
  listPrefrioTunnels,
} from '../services/prefrioApi';
import {
  loadPrefrioCache,
  savePrefrioCache,
} from '../services/prefrioOfflineStore';
import { colors } from '../theme/colors';
import { PrefrioScreen } from './PrefrioScreen';

type PrefrioWorkspaceProps = {
  auth: AuthSession;
  baseUrl: string | null;
  onLogout: () => void;
};

type WorkspaceView = 'pendientes' | 'tuneles';

type CandidateGroup = {
  key: PrefrioFolioCandidate['condicion_termica'];
  label: string;
  items: PrefrioFolioCandidate[];
};

type FolioFilters = {
  exportadora: string;
  especie: string;
  variedad: string;
  condicionSag: '' | 'con' | 'sin';
  csg: string;
  fechaIngreso: string;
  condicionTermica: string;
};

type FilterOption = {
  value: string;
  label: string;
};

const EMPTY_FILTERS: FolioFilters = {
  exportadora: '',
  especie: '',
  variedad: '',
  condicionSag: '',
  csg: '',
  fechaIngreso: '',
  condicionTermica: '',
};

const THERMAL_FILTER_OPTIONS: FilterOption[] = [
  { value: 'pendiente_prefrio', label: 'Pendiente de Prefrío' },
  { value: 'requiere_reproceso', label: 'Requiere reproceso' },
  { value: 'retenido', label: 'Retenido' },
];

const LOADABLE_PROCESS_STATES = new Set(['borrador', 'cargando', 'listo_para_iniciar']);

export function PrefrioWorkspaceScreen({ auth, baseUrl, onLogout }: PrefrioWorkspaceProps) {
  const loadingFolio = useRef(false);
  const previousView = useRef<WorkspaceView>('pendientes');
  const synchronizing = useRef(false);
  const userId = auth.usuario.id;
  const deviceId = auth.dispositivo.id;
  const canOperate = auth.usuario.capacidades.puede_operar_prefrio === true;
  const [activeView, setActiveView] = useState<WorkspaceView>('pendientes');
  const [folios, setFolios] = useState<PrefrioFolioCandidate[]>([]);
  const [processes, setProcesses] = useState<PrefrioProcess[]>([]);
  const [tunnels, setTunnels] = useState<PrefrioMobileCache['tunnels']>([]);
  const [filters, setFilters] = useState<FolioFilters>({ ...EMPTY_FILTERS });
  const [selectedFolioId, setSelectedFolioId] = useState<string | null>(null);
  const [selectedProcessId, setSelectedProcessId] = useState<string | null>(null);
  const [selectedPositionId, setSelectedPositionId] = useState<string | null>(null);
  const [temperature, setTemperature] = useState('');
  const [busy, setBusy] = useState(true);
  const [online, setOnline] = useState(Boolean(baseUrl));
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const selectedFolio = useMemo(
    () => folios.find((item) => item.id === selectedFolioId) ?? null,
    [folios, selectedFolioId],
  );
  const loadableProcesses = useMemo(
    () => processes.filter((item) => LOADABLE_PROCESS_STATES.has(item.estado)),
    [processes],
  );
  const selectedProcess = useMemo(
    () => loadableProcesses.find((item) => item.id === selectedProcessId) ?? null,
    [loadableProcesses, selectedProcessId],
  );
  const selectedTunnel = useMemo(
    () => tunnels.find((item) => item.id === selectedProcess?.tunel.id) ?? null,
    [tunnels, selectedProcess],
  );
  const occupiedPositionIds = useMemo(() => new Set(
    selectedProcess?.folios
      .filter((item) => !['retirado', 'cancelado'].includes(item.estado))
      .map((item) => item.posicion?.id)
      .filter((id): id is string => Boolean(id)) ?? [],
  ), [selectedProcess]);
  const freePositions = useMemo(
    () => selectedTunnel?.posiciones.filter((item) => item.activa && !occupiedPositionIds.has(item.id)) ?? [],
    [selectedTunnel, occupiedPositionIds],
  );
  const hasRequiredFilters = filters.exportadora !== '' && filters.especie !== '';
  const filterBase = useMemo(
    () => folios.filter((item) => (
      (filters.exportadora === '' || item.exportadora === filters.exportadora)
      && (filters.especie === '' || item.especie === filters.especie)
    )),
    [folios, filters.exportadora, filters.especie],
  );
  const filteredFolios = useMemo(() => {
    if (!hasRequiredFilters) return [];

    return filterBase.filter((item) => (
      (filters.variedad === '' || item.variedad === filters.variedad)
      && (
        filters.condicionSag === ''
        || (filters.condicionSag === 'con' ? item.tiene_condicion_sag : !item.tiene_condicion_sag)
      )
      && (filters.csg === '' || item.csg === filters.csg)
      && (filters.fechaIngreso === '' || item.fecha_ingreso?.slice(0, 10) === filters.fechaIngreso)
      && (filters.condicionTermica === '' || item.condicion_termica === filters.condicionTermica)
    ));
  }, [filterBase, filters, hasRequiredFilters]);
  const exportadoraOptions = useMemo(
    () => uniqueFilterOptions([...folios.map((item) => item.exportadora), filters.exportadora]),
    [folios, filters.exportadora],
  );
  const especieOptions = useMemo(
    () => uniqueFilterOptions(
      [
        ...folios
          .filter((item) => filters.exportadora === '' || item.exportadora === filters.exportadora)
          .map((item) => item.especie),
        filters.especie,
      ],
    ),
    [folios, filters.exportadora, filters.especie],
  );
  const variedadOptions = useMemo(
    () => uniqueFilterOptions([...filterBase.map((item) => item.variedad), filters.variedad]),
    [filterBase, filters.variedad],
  );
  const csgOptions = useMemo(
    () => uniqueFilterOptions([...filterBase.map((item) => item.csg), filters.csg]),
    [filterBase, filters.csg],
  );
  const dateOptions = useMemo(
    () => uniqueFilterOptions(
      [...filterBase.map((item) => item.fecha_ingreso?.slice(0, 10) ?? ''), filters.fechaIngreso],
      formatFilterDate,
    ),
    [filterBase, filters.fechaIngreso],
  );
  const groups = useMemo<CandidateGroup[]>(() => ([
    {
      key: 'pendiente_prefrio',
      label: 'Pendientes nuevos',
      items: filteredFolios.filter((item) => item.condicion_termica === 'pendiente_prefrio'),
    },
    {
      key: 'requiere_reproceso',
      label: 'Requieren reproceso',
      items: filteredFolios.filter((item) => item.condicion_termica === 'requiere_reproceso'),
    },
    {
      key: 'retenido',
      label: 'Retenidos',
      items: filteredFolios.filter((item) => item.condicion_termica === 'retenido'),
    },
  ]), [filteredFolios]);

  useEffect(() => {
    void initialize();
  }, []);

  useEffect(() => {
    if (!selectedProcessId && loadableProcesses.length) {
      setSelectedProcessId(loadableProcesses[0].id);
    }
  }, [loadableProcesses, selectedProcessId]);

  useEffect(() => {
    setSelectedPositionId(freePositions[0]?.id ?? null);
  }, [selectedProcessId, freePositions.length]);

  useEffect(() => {
    const previous = previousView.current;
    previousView.current = activeView;
    if (previous === 'tuneles' && activeView === 'pendientes') {
      void synchronize(false);
    }
  }, [activeView]);

  useEffect(() => {
    const timer = setInterval(() => {
      if (activeView === 'pendientes') void synchronize(false);
    }, 30000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active' && activeView === 'pendientes') void synchronize(false);
    });
    return () => {
      clearInterval(timer);
      subscription.remove();
    };
  }, [activeView, baseUrl, auth.token]);

  async function initialize() {
    setBusy(true);
    try {
      const cached = await loadPrefrioCache(userId, deviceId);
      if (cached) {
        setFolios(cached.eligible_folios);
        setProcesses(cached.processes);
        setTunnels(cached.tunnels);
      }
      await synchronize(false);
    } catch (reason) {
      setError(messageFrom(reason));
    } finally {
      setBusy(false);
    }
  }

  async function synchronize(showNotice = true) {
    if (!baseUrl) {
      setOnline(false);
      if (showNotice) setError('La PDA está sin conexión. Se muestra la última bandeja sincronizada.');
      return;
    }
    if (synchronizing.current) return;

    synchronizing.current = true;
    try {
      const [nextTunnels, nextProcesses, nextFolios] = await Promise.all([
        listPrefrioTunnels(baseUrl, auth.token),
        listPrefrioProcesses(baseUrl, auth.token),
        listEligiblePrefrioFolios(baseUrl, auth.token),
      ]);
      const nextCache: PrefrioMobileCache = {
        tunnels: nextTunnels,
        processes: nextProcesses,
        eligible_folios: nextFolios,
        synced_at: new Date().toISOString(),
      };
      setTunnels(nextTunnels);
      setProcesses(nextProcesses);
      setFolios(nextFolios);
      await savePrefrioCache(userId, deviceId, nextCache);
      setOnline(true);
      setError('');
      if (showNotice) setNotice('Bandeja de Prefrío actualizada.');
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 401) {
        onLogout();
        return;
      }
      if (reason instanceof ApiError && reason.status === 0) setOnline(false);
      setError(messageFrom(reason));
    } finally {
      synchronizing.current = false;
    }
  }

  function openCandidate(candidate: PrefrioFolioCandidate) {
    setSelectedFolioId(candidate.id);
    setSelectedProcessId(loadableProcesses[0]?.id ?? null);
    setTemperature('');
    setError('');
    setNotice('');
  }

  function closeCandidate() {
    setSelectedFolioId(null);
    setSelectedPositionId(null);
    setTemperature('');
  }

  function updateFilter<K extends keyof FolioFilters>(key: K, value: FolioFilters[K]) {
    setFilters((current) => {
      if (key === 'exportadora') {
        return {
          ...EMPTY_FILTERS,
          exportadora: value as string,
        };
      }
      if (key === 'especie') {
        return {
          ...current,
          especie: value as string,
          variedad: '',
          condicionSag: '',
          csg: '',
          fechaIngreso: '',
          condicionTermica: '',
        };
      }

      return { ...current, [key]: value };
    });
  }

  function clearFilters() {
    setFilters({ ...EMPTY_FILTERS });
  }

  async function loadSelectedFolio() {
    if (loadingFolio.current || busy) return;
    if (!selectedFolio || !selectedProcess || !selectedPositionId) {
      setError('Selecciona un proceso activo y una posición libre.');
      return;
    }
    if (!baseUrl) {
      setError('La carga rápida desde la bandeja requiere conexión. Usa Túneles para operar con la bandeja offline.');
      return;
    }
    if (!canOperate) {
      setError('Tu perfil solo puede consultar la bandeja de Prefrío.');
      return;
    }

    const parsedTemperature = temperature.trim() === ''
      ? undefined
      : Number(temperature.replace(',', '.'));
    if (parsedTemperature !== undefined && !Number.isFinite(parsedTemperature)) {
      setError('La temperatura inicial no es válida.');
      return;
    }

    const reopensAssembly = selectedProcess.estado === 'listo_para_iniciar';
    loadingFolio.current = true;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const updatedProcess = await executePrefrioCommand(
        baseUrl,
        auth.token,
        `/api/prefrio/procesos/${selectedProcess.id}/folios`,
        {
          operacion_id: Crypto.randomUUID(),
          version_conocida: selectedProcess.version,
          folio_id: selectedFolio.id,
          posicion_tunel_prefrio_id: selectedPositionId,
          ...(parsedTemperature !== undefined ? { temperatura_inicial: parsedTemperature } : {}),
          ocurrido_at: new Date().toISOString(),
        },
      );
      const nextProcesses = [
        updatedProcess,
        ...processes.filter((item) => item.id !== updatedProcess.id),
      ];
      const nextFolios = folios.filter((item) => item.id !== selectedFolio.id);
      setProcesses(nextProcesses);
      setFolios(nextFolios);
      await savePrefrioCache(userId, deviceId, {
        tunnels,
        processes: nextProcesses,
        eligible_folios: nextFolios,
        synced_at: new Date().toISOString(),
      });
      setOnline(true);
      setNotice(
        `${selectedFolio.numero_folio} cargado en ${positionLabel(selectedPositionId, freePositions)} del proceso ${updatedProcess.codigo}.`
        + (reopensAssembly ? ' El proceso volvió a Cargando; confirma nuevamente el armado antes de iniciarlo.' : ''),
      );
      closeCandidate();
    } catch (reason) {
      if (reason instanceof ApiError && reason.status === 0) setOnline(false);
      setError(messageFrom(reason));
      await synchronize(false);
    } finally {
      loadingFolio.current = false;
      setBusy(false);
    }
  }

  if (activeView === 'tuneles') {
    return (
      <View style={styles.screen}>
        <WorkspaceTabs activeView={activeView} pendingCount={folios.length} onChange={setActiveView} />
        <View style={styles.tunnelWorkspace}>
          <PrefrioScreen auth={auth} baseUrl={baseUrl} onLogout={onLogout} />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <WorkspaceTabs activeView={activeView} pendingCount={folios.length} onChange={setActiveView} />
      <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <View>
            <Text style={styles.eyebrow}>ESTIBA WMS · PRE-FRÍO</Text>
            <Text style={styles.title}>Folios pendientes de ingreso</Text>
            <Text style={styles.subtitle}>Pallets y saldos aprobados en Validación que todavía no pertenecen a un proceso activo.</Text>
          </View>
          <View style={styles.headerActions}>
            <View style={[styles.connection, online ? styles.online : styles.offline]}>
              <Text style={styles.connectionText}>{online ? 'EN LÍNEA' : 'SIN CONEXIÓN'}</Text>
            </View>
            <Pressable disabled={busy} onPress={() => void synchronize()} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>↻ Sincronizar</Text>
            </Pressable>
            <Pressable onPress={onLogout} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>Cerrar turno</Text>
            </Pressable>
          </View>
        </View>

        {notice ? <Text style={styles.notice}>{notice}</Text> : null}
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.metrics}>
          <Metric label="PENDIENTES NUEVOS" value={String(groups[0].items.length)} />
          <Metric label="REPROCESOS" value={String(groups[1].items.length)} warning={groups[1].items.length > 0} />
          <Metric label="RETENIDOS" value={String(groups[2].items.length)} warning={groups[2].items.length > 0} />
          <Metric label="PROCESOS CARGABLES" value={String(loadableProcesses.length)} />
        </View>

        <View style={styles.filtersPanel}>
          <View style={styles.filtersHeader}>
            <View>
              <Text style={styles.filtersTitle}>Filtrar folios</Text>
              <Text style={styles.filtersHint}>Cliente/exportadora y especie son obligatorios. Los demás filtros son opcionales y combinables.</Text>
            </View>
            <Pressable disabled={busy} onPress={clearFilters} style={[styles.clearFiltersButton, busy && styles.disabled]}>
              <Text style={styles.clearFiltersText}>Limpiar</Text>
            </Pressable>
          </View>
          <View style={styles.filterGrid}>
            <FilterSelect
              disabled={busy}
              label="1. Cliente / exportadora *"
              onChange={(value) => updateFilter('exportadora', value)}
              options={exportadoraOptions}
              placeholder="Seleccionar cliente"
              required
              value={filters.exportadora}
            />
            <FilterSelect
              disabled={busy || !filters.exportadora}
              label="2. Especie *"
              onChange={(value) => updateFilter('especie', value)}
              options={especieOptions}
              placeholder="Seleccionar especie"
              required
              value={filters.especie}
            />
            <FilterSelect
              disabled={busy || !hasRequiredFilters}
              label="3. Variedad"
              onChange={(value) => updateFilter('variedad', value)}
              options={variedadOptions}
              placeholder="Todas las variedades"
              value={filters.variedad}
            />
            <FilterSelect
              disabled={busy || !hasRequiredFilters}
              label="4. Condición SAG"
              onChange={(value) => updateFilter('condicionSag', value as FolioFilters['condicionSag'])}
              options={[
                { value: 'con', label: 'Con condición' },
                { value: 'sin', label: 'Sin condición' },
              ]}
              placeholder="Con o sin condición"
              value={filters.condicionSag}
            />
            <FilterSelect
              disabled={busy || !hasRequiredFilters}
              label="5. CSG"
              onChange={(value) => updateFilter('csg', value)}
              options={csgOptions}
              placeholder="Todos los CSG"
              value={filters.csg}
            />
            <FilterSelect
              disabled={busy || !hasRequiredFilters}
              label="6. Fecha de ingreso"
              onChange={(value) => updateFilter('fechaIngreso', value)}
              options={dateOptions}
              placeholder="Todas las fechas"
              value={filters.fechaIngreso}
            />
            <FilterSelect
              disabled={busy || !hasRequiredFilters}
              label="7. Condición térmica"
              onChange={(value) => updateFilter('condicionTermica', value)}
              options={THERMAL_FILTER_OPTIONS}
              placeholder="Todas las condiciones"
              value={filters.condicionTermica}
            />
          </View>
        </View>

        {groups.map((group) => group.items.length ? (
          <View key={group.key} style={styles.group}>
            <View style={styles.groupHeader}>
              <Text style={styles.groupTitle}>{group.label}</Text>
              <Text style={styles.groupCount}>{group.items.length}</Text>
            </View>
            <View style={styles.cards}>
              {group.items.map((candidate) => (
                <CandidateCard key={candidate.id} candidate={candidate} canOperate={canOperate} onOpen={openCandidate} />
              ))}
            </View>
          </View>
        ) : null)}

        {!filteredFolios.length ? (
          <View style={styles.emptyPanel}>
            <Text style={styles.emptyTitle}>
              {!hasRequiredFilters
                ? 'Selecciona cliente/exportadora y especie'
                : folios.length
                  ? 'Sin coincidencias'
                  : 'No existen folios esperando Prefrío'}
            </Text>
            <Text style={styles.subtitle}>
              {!hasRequiredFilters
                ? 'Los filtros opcionales se habilitarán al completar las dos selecciones obligatorias.'
                : folios.length
                  ? 'Prueba otra combinación de filtros para llegar a los folios.'
                  : 'Los pallets aprobados en Validación aparecerán aquí al sincronizar.'}
            </Text>
          </View>
        ) : null}
      </ScrollView>

      <CandidateModal
        busy={busy}
        canOperate={canOperate}
        folio={selectedFolio}
        freePositions={freePositions}
        loadableProcesses={loadableProcesses}
        selectedPositionId={selectedPositionId}
        selectedProcessId={selectedProcessId}
        temperature={temperature}
        onClose={closeCandidate}
        onConfirm={() => void loadSelectedFolio()}
        onPositionChange={setSelectedPositionId}
        onProcessChange={setSelectedProcessId}
        onTemperatureChange={setTemperature}
        onOpenTunnels={() => {
          closeCandidate();
          setActiveView('tuneles');
        }}
      />

      {busy ? (
        <View style={styles.busyOverlay}>
          <ActivityIndicator color={colors.cyan} size="large" />
          <Text style={styles.busyText}>Actualizando Prefrío…</Text>
        </View>
      ) : null}
    </View>
  );
}

function WorkspaceTabs({ activeView, pendingCount, onChange }: { activeView: WorkspaceView; pendingCount: number; onChange: (view: WorkspaceView) => void }) {
  return (
    <View style={styles.tabs}>
      <Pressable onPress={() => onChange('pendientes')} style={[styles.tab, activeView === 'pendientes' && styles.tabActive]}>
        <Text style={[styles.tabText, activeView === 'pendientes' && styles.tabTextActive]}>Pendientes ({pendingCount})</Text>
      </Pressable>
      <Pressable onPress={() => onChange('tuneles')} style={[styles.tab, activeView === 'tuneles' && styles.tabActive]}>
        <Text style={[styles.tabText, activeView === 'tuneles' && styles.tabTextActive]}>Túneles y procesos</Text>
      </Pressable>
    </View>
  );
}

function uniqueFilterOptions(
  values: Array<string | null | undefined>,
  labeler: (value: string) => string = (value) => value,
): FilterOption[] {
  return [...new Set(values.filter((value): value is string => Boolean(value)))]
    .sort((left, right) => left.localeCompare(right, 'es'))
    .map((value) => ({ value, label: labeler(value) }));
}

function formatFilterDate(value: string): string {
  const [year, month, day] = value.split('-');
  return year && month && day ? day + '-' + month + '-' + year : value;
}

function FilterSelect({
  disabled,
  label,
  onChange,
  options,
  placeholder,
  required = false,
  value,
}: {
  disabled: boolean;
  label: string;
  onChange: (value: string) => void;
  options: FilterOption[];
  placeholder: string;
  required?: boolean;
  value: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.value === value);

  return (
    <View style={styles.filterField}>
      <Text style={styles.filterLabel}>{label}</Text>
      <Pressable
        disabled={disabled}
        onPress={() => setOpen(true)}
        style={[styles.filterTrigger, disabled && styles.disabled]}
      >
        <Text numberOfLines={1} style={[styles.filterTriggerText, !selected && styles.filterPlaceholder]}>
          {selected?.label ?? placeholder}
        </Text>
        <Text style={styles.filterChevron}>⌄</Text>
      </Pressable>
      <Modal
        animationType="slide"
        onRequestClose={() => setOpen(false)}
        transparent
        visible={open}
      >
        <View style={styles.filterModalBackdrop}>
          <View style={styles.filterModal}>
            <View style={styles.filterModalHeader}>
              <Text style={styles.filterModalTitle}>{label}</Text>
              <Pressable onPress={() => setOpen(false)}>
                <Text style={styles.modalClose}>×</Text>
              </Pressable>
            </View>
            {!required ? (
              <Pressable
                onPress={() => {
                  onChange('');
                  setOpen(false);
                }}
                style={[styles.filterOption, !value && styles.filterOptionSelected]}
              >
                <Text style={styles.filterOptionText}>{placeholder}</Text>
              </Pressable>
            ) : null}
            <ScrollView contentContainerStyle={styles.filterOptions} keyboardShouldPersistTaps="handled">
              {options.map((option) => (
                <Pressable
                  key={option.value}
                  onPress={() => {
                    onChange(option.value);
                    setOpen(false);
                  }}
                  style={[styles.filterOption, option.value === value && styles.filterOptionSelected]}
                >
                  <Text style={styles.filterOptionText}>{option.label}</Text>
                  {option.value === value ? <Text style={styles.filterOptionCheck}>✓</Text> : null}
                </Pressable>
              ))}
              {!options.length ? <Text style={styles.filterEmpty}>No existen opciones para los filtros actuales.</Text> : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function CandidateCard({ candidate, canOperate, onOpen }: { candidate: PrefrioFolioCandidate; canOperate: boolean; onOpen: (candidate: PrefrioFolioCandidate) => void }) {
  return (
    <Pressable onPress={() => onOpen(candidate)} style={styles.card}>
      <View style={styles.cardHeader}>
        <View>
          <Text style={styles.folioNumber}>{candidate.numero_folio}</Text>
          <Text style={styles.cardMeta}>{candidate.tipo_bulto === 'pallet' ? 'Pallet completo' : 'Saldo'}{candidate.cantidad_cajas ? ` · ${candidate.cantidad_cajas} cajas` : ''}</Text>
        </View>
        <Text style={[styles.statusBadge, candidate.condicion_termica === 'pendiente_prefrio' ? styles.pendingBadge : candidate.condicion_termica === 'requiere_reproceso' ? styles.reprocessBadge : styles.retainedBadge]}>
          {thermalLabel(candidate.condicion_termica)}
        </Text>
      </View>
      <Text style={styles.article}>{[candidate.especie, candidate.variedad, candidate.calibre, candidate.envase].filter(Boolean).join(' · ') || 'Artículo sin detalle'}</Text>
      <Text style={styles.detail}>Cliente: {candidate.exportadora ?? '—'} · Marca: {candidate.marca ?? '—'}</Text>
      <Text style={styles.detail}>CSG: {candidate.csg ?? '—'} · Predio: {candidate.predio ?? '—'}</Text>
      <View style={styles.cardFooter}>
        <Text style={styles.dateText}>Validado {formatDateTime(candidate.fecha_ingreso)}</Text>
        <Text style={styles.openText}>{canOperate ? 'Revisar y cargar →' : 'Ver detalle →'}</Text>
      </View>
    </Pressable>
  );
}

function CandidateModal({
  busy,
  canOperate,
  folio,
  freePositions,
  loadableProcesses,
  selectedPositionId,
  selectedProcessId,
  temperature,
  onClose,
  onConfirm,
  onPositionChange,
  onProcessChange,
  onTemperatureChange,
  onOpenTunnels,
}: {
  busy: boolean;
  canOperate: boolean;
  folio: PrefrioFolioCandidate | null;
  freePositions: PrefrioTunnelPosition[];
  loadableProcesses: PrefrioProcess[];
  selectedPositionId: string | null;
  selectedProcessId: string | null;
  temperature: string;
  onClose: () => void;
  onConfirm: () => void;
  onPositionChange: (id: string) => void;
  onProcessChange: (id: string) => void;
  onTemperatureChange: (value: string) => void;
  onOpenTunnels: () => void;
}) {
  if (!folio) return null;
  const selectedProcess = loadableProcesses.find((process) => process.id === selectedProcessId) ?? null;

  return (
    <Modal animationType="slide" transparent visible onRequestClose={() => { if (!busy) onClose(); }}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          <ScrollView contentContainerStyle={styles.modalContent} keyboardShouldPersistTaps="handled">
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.eyebrow}>{thermalLabel(folio.condicion_termica)}</Text>
                <Text style={styles.modalTitle}>{folio.numero_folio}</Text>
              </View>
              <Pressable disabled={busy} onPress={onClose}><Text style={styles.modalClose}>×</Text></Pressable>
            </View>

            <View style={styles.detailGrid}>
              <Detail label="Bulto" value={`${folio.tipo_bulto === 'pallet' ? 'Pallet completo' : 'Saldo'}${folio.cantidad_cajas ? ` · ${folio.cantidad_cajas} cajas` : ''}`} />
              <Detail label="Artículo" value={[folio.especie, folio.variedad, folio.calibre, folio.envase].filter(Boolean).join(' · ') || 'Sin detalle'} />
              <Detail label="Cliente / marca" value={`${folio.exportadora ?? '—'} · ${folio.marca ?? '—'}`} />
              <Detail label="Origen" value={`CSG ${folio.csg ?? '—'} · ${folio.predio ?? '—'}`} />
              <Detail label="Categoría" value={folio.categoria ?? '—'} />
              <Detail label="Validado" value={formatDateTime(folio.fecha_ingreso)} />
            </View>

            {canOperate ? (
              <>
                <Text style={styles.modalSectionTitle}>Proceso de destino</Text>
                {loadableProcesses.length ? (
                  <View style={styles.optionGrid}>
                    {loadableProcesses.map((process) => (
                      <Pressable disabled={busy} key={process.id} onPress={() => onProcessChange(process.id)} style={[styles.optionCard, process.id === selectedProcessId && styles.optionCardSelected, busy && styles.disabled]}>
                        <Text style={styles.optionTitle}>{process.codigo}</Text>
                        <Text style={styles.optionDetail}>{process.tunel.codigo} · {process.folios.filter((item) => !['retirado', 'cancelado'].includes(item.estado)).length}/{process.tunel.capacidad_posiciones}</Text>
                        <Text style={styles.optionState}>{processStateLabel(process.estado)}</Text>
                      </Pressable>
                    ))}
                  </View>
                ) : (
                  <View style={styles.emptyProcess}>
                    <Text style={styles.emptyTitle}>No existe un proceso cargable</Text>
                    <Text style={styles.subtitle}>Crea o abre un proceso en la vista de Túneles antes de asignar este folio.</Text>
                    <Pressable disabled={busy} onPress={onOpenTunnels} style={[styles.secondaryButton, busy && styles.disabled]}><Text style={styles.secondaryButtonText}>Abrir Túneles</Text></Pressable>
                  </View>
                )}

                {loadableProcesses.length ? (
                  <>
                    {selectedProcess?.estado === 'listo_para_iniciar' ? (
                      <Text style={styles.reopenNotice}>
                        Al cargar este folio, el proceso volverá a Cargando y deberás confirmar nuevamente el armado antes de iniciarlo.
                      </Text>
                    ) : null}
                    <Text style={styles.modalSectionTitle}>Posición libre</Text>
                    <View style={styles.positionGrid}>
                      {freePositions.map((position) => (
                        <Pressable disabled={busy} key={position.id} onPress={() => onPositionChange(position.id)} style={[styles.position, position.id === selectedPositionId && styles.positionSelected, busy && styles.disabled]}>
                          <Text style={styles.positionText}>{position.etiqueta}</Text>
                        </Pressable>
                      ))}
                      {!freePositions.length ? <Text style={styles.error}>El proceso seleccionado no posee posiciones libres.</Text> : null}
                    </View>
                    <Text style={styles.inputLabel}>Temperatura inicial opcional</Text>
                    <TextInput
                      editable={!busy}
                      keyboardType="decimal-pad"
                      onChangeText={onTemperatureChange}
                      placeholder="Ej.: 8,5"
                      placeholderTextColor={colors.muted}
                      style={styles.temperatureInput}
                      value={temperature}
                    />
                  </>
                ) : null}
              </>
            ) : (
              <Text style={styles.readOnlyNotice}>Tu perfil puede consultar los folios, pero no cargarlos a un túnel.</Text>
            )}

            <View style={styles.modalActions}>
              <Pressable disabled={busy} onPress={onClose} style={[styles.secondaryButton, busy && styles.disabled]}><Text style={styles.secondaryButtonText}>Cerrar</Text></Pressable>
              {canOperate && loadableProcesses.length ? (
                <Pressable disabled={busy || !selectedPositionId} onPress={onConfirm} style={[styles.primaryButton, (busy || !selectedPositionId) && styles.disabled]}>
                  <Text style={styles.primaryButtonText}>{busy ? 'Cargando…' : 'Cargar al túnel'}</Text>
                </Pressable>
              ) : null}
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return <View style={styles.detailBox}><Text style={styles.inputLabel}>{label}</Text><Text style={styles.detailValue}>{value}</Text></View>;
}

function Metric({ label, value, warning = false }: { label: string; value: string; warning?: boolean }) {
  return <View style={[styles.metric, warning && styles.metricWarning]}><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricLabel}>{label}</Text></View>;
}

function thermalLabel(value: PrefrioFolioCandidate['condicion_termica']) {
  return value === 'pendiente_prefrio' ? 'Pendiente de Prefrío' : value === 'requiere_reproceso' ? 'Reproceso' : 'Retenido';
}

function processStateLabel(value: PrefrioProcess['estado']) {
  if (value === 'borrador') return 'Borrador';
  if (value === 'cargando') return 'Cargando';
  if (value === 'listo_para_iniciar') return 'Listo para iniciar';
  return value;
}

function formatDateTime(value: string | null) {
  if (!value) return 'sin fecha';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'sin fecha';
  return new Intl.DateTimeFormat('es-CL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date);
}

function positionLabel(positionId: string, positions: PrefrioTunnelPosition[]) {
  return positions.find((item) => item.id === positionId)?.etiqueta ?? 'la posición seleccionada';
}

function messageFrom(reason: unknown) {
  return reason instanceof Error ? reason.message : 'Ocurrió un error inesperado en Prefrío.';
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  tunnelWorkspace: { flex: 1 },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: 18, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: colors.border, backgroundColor: colors.backgroundDeep },
  tab: { paddingHorizontal: 16, paddingVertical: 9, borderRadius: 10, borderWidth: 1, borderColor: colors.border },
  tabActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  tabText: { color: colors.muted, fontWeight: '900', fontSize: 11 },
  tabTextActive: { color: colors.cyan },
  page: { padding: 18, gap: 16, paddingBottom: 44 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 18, padding: 18, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 9, flexWrap: 'wrap', justifyContent: 'flex-end' },
  eyebrow: { color: colors.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.2 },
  title: { color: colors.text, fontSize: 25, fontWeight: '900', marginTop: 4 },
  subtitle: { color: colors.muted, marginTop: 6, lineHeight: 19 },
  connection: { paddingHorizontal: 11, paddingVertical: 8, borderRadius: 999, borderWidth: 1 },
  online: { borderColor: colors.green, backgroundColor: colors.greenDark },
  offline: { borderColor: colors.red, backgroundColor: colors.blocked },
  connectionText: { color: colors.text, fontSize: 10, fontWeight: '900' },
  secondaryButton: { paddingHorizontal: 13, paddingVertical: 10, borderRadius: 10, borderWidth: 1, borderColor: colors.cyanDark, alignItems: 'center' },
  secondaryButtonText: { color: colors.cyan, fontSize: 11, fontWeight: '900' },
  primaryButton: { paddingHorizontal: 18, paddingVertical: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.green, backgroundColor: colors.greenDark, alignItems: 'center' },
  primaryButtonText: { color: colors.text, fontSize: 11, fontWeight: '900', textTransform: 'uppercase' },
  disabled: { opacity: .4 },
  notice: { color: colors.text, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.green, backgroundColor: colors.greenDark, fontWeight: '800' },
  error: { color: colors.text, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.red, backgroundColor: colors.blocked, fontWeight: '800' },
  metrics: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
  metric: { minWidth: 170, flex: 1, padding: 15, borderRadius: 13, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  metricWarning: { borderColor: colors.amber },
  metricValue: { color: colors.text, fontSize: 24, fontWeight: '900' },
  metricLabel: { color: colors.muted, fontSize: 10, fontWeight: '900', marginTop: 4 },
  filtersPanel: { gap: 13, padding: 16, borderRadius: 14, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  filtersHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 },
  filtersTitle: { color: colors.text, fontSize: 17, fontWeight: '900' },
  filtersHint: { color: colors.muted, fontSize: 11, lineHeight: 17, marginTop: 4, maxWidth: 700 },
  clearFiltersButton: { paddingHorizontal: 11, paddingVertical: 8, borderRadius: 9, borderWidth: 1, borderColor: colors.cyanDark },
  clearFiltersText: { color: colors.cyan, fontSize: 10, fontWeight: '900' },
  filterGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  filterField: { flexGrow: 1, flexBasis: 220, minWidth: 190, gap: 5 },
  filterLabel: { color: colors.muted, fontSize: 9, fontWeight: '900', textTransform: 'uppercase' },
  filterTrigger: { minHeight: 46, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, paddingHorizontal: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  filterTriggerText: { flex: 1, color: colors.text, fontSize: 11, fontWeight: '800' },
  filterPlaceholder: { color: colors.muted },
  filterChevron: { color: colors.cyan, fontSize: 17, fontWeight: '900' },
  filterModalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,.72)' },
  filterModal: { maxHeight: '82%', padding: 18, borderTopLeftRadius: 18, borderTopRightRadius: 18, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panelStrong },
  filterModalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 10 },
  filterModalTitle: { color: colors.text, fontSize: 18, fontWeight: '900' },
  filterOptions: { gap: 8, paddingBottom: 12 },
  filterOption: { minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingHorizontal: 13, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  filterOptionSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  filterOptionText: { flex: 1, color: colors.text, fontWeight: '800' },
  filterOptionCheck: { color: colors.cyan, fontSize: 18, fontWeight: '900' },
  filterEmpty: { color: colors.muted, padding: 14, textAlign: 'center' },
  group: { gap: 10 },
  groupHeader: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  groupTitle: { color: colors.text, fontSize: 18, fontWeight: '900' },
  groupCount: { color: colors.cyan, fontSize: 11, fontWeight: '900', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999, backgroundColor: colors.selected },
  cards: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  card: { flexGrow: 1, flexBasis: 310, maxWidth: 470, padding: 16, borderRadius: 14, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10 },
  folioNumber: { color: colors.text, fontSize: 18, fontWeight: '900' },
  cardMeta: { color: colors.muted, fontSize: 10, marginTop: 3 },
  statusBadge: { overflow: 'hidden', paddingHorizontal: 9, paddingVertical: 5, borderRadius: 999, color: colors.text, fontSize: 9, fontWeight: '900', textTransform: 'uppercase' },
  pendingBadge: { backgroundColor: colors.free },
  reprocessBadge: { backgroundColor: colors.amberDark },
  retainedBadge: { backgroundColor: colors.blocked },
  article: { color: colors.cyan, fontWeight: '900', marginTop: 13 },
  detail: { color: colors.text, fontSize: 11, marginTop: 5 },
  cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginTop: 15, paddingTop: 10, borderTopWidth: 1, borderTopColor: colors.borderSoft },
  dateText: { color: colors.muted, fontSize: 10 },
  openText: { color: colors.cyan, fontSize: 10, fontWeight: '900' },
  emptyPanel: { alignItems: 'center', padding: 32, borderRadius: 14, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panel },
  emptyTitle: { color: colors.text, fontSize: 17, fontWeight: '900' },
  modalBackdrop: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 18, backgroundColor: 'rgba(0,0,0,.76)' },
  modalCard: { width: '100%', maxWidth: 920, maxHeight: '94%', borderRadius: 17, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.panelStrong },
  modalContent: { padding: 20 },
  modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12, marginBottom: 15 },
  modalTitle: { color: colors.text, fontSize: 25, fontWeight: '900', marginTop: 3 },
  modalClose: { color: colors.text, fontSize: 29, fontWeight: '900', paddingHorizontal: 8 },
  detailGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9 },
  detailBox: { flexGrow: 1, flexBasis: 260, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.borderSoft, backgroundColor: colors.backgroundDeep },
  inputLabel: { color: colors.muted, fontSize: 9, fontWeight: '900', textTransform: 'uppercase', marginBottom: 5 },
  detailValue: { color: colors.text, fontWeight: '800' },
  modalSectionTitle: { color: colors.text, fontSize: 15, fontWeight: '900', marginTop: 18, marginBottom: 9 },
  optionGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9 },
  optionCard: { minWidth: 180, flexGrow: 1, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  optionCardSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  optionTitle: { color: colors.text, fontWeight: '900' },
  optionDetail: { color: colors.muted, fontSize: 10, marginTop: 4 },
  optionState: { color: colors.cyan, fontSize: 10, fontWeight: '900', marginTop: 5, textTransform: 'uppercase' },
  reopenNotice: { color: colors.text, padding: 11, borderRadius: 10, borderWidth: 1, borderColor: colors.amber, backgroundColor: colors.amberDark, fontWeight: '800', lineHeight: 18 },
  positionGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 7 },
  position: { paddingHorizontal: 10, paddingVertical: 9, borderRadius: 8, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.backgroundDeep },
  positionSelected: { borderColor: colors.cyan, backgroundColor: colors.selected },
  positionText: { color: colors.text, fontSize: 10, fontWeight: '900' },
  temperatureInput: { minHeight: 48, paddingHorizontal: 13, borderRadius: 10, borderWidth: 1, borderColor: colors.border, color: colors.text, backgroundColor: colors.backgroundDeep },
  emptyProcess: { gap: 10, padding: 14, borderRadius: 11, borderWidth: 1, borderColor: colors.amber, backgroundColor: colors.amberDark },
  readOnlyNotice: { color: colors.text, marginTop: 18, padding: 13, borderRadius: 10, borderWidth: 1, borderColor: colors.cyanDark, backgroundColor: colors.selected },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 9, marginTop: 20 },
  busyOverlay: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: 'rgba(8,12,16,.74)' },
  busyText: { color: colors.text, fontWeight: '900' },
});
