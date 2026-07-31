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
    'mobile/src/domain/estiba.ts',
    """export type Occupancy = {
  ocupadas: number;
  total: number;
  porcentaje: number;
};
""",
    """export type Occupancy = {
  ocupadas: number;
  sin_posicion: number;
  total: number;
  porcentaje: number;
};
""",
)
replace(
    'mobile/src/domain/estiba.ts',
    "    posicion: { id: string; etiqueta: string | null };\n",
    "    posicion: { id: string; etiqueta: string | null } | null;\n",
)
replace(
    'mobile/src/domain/estiba.ts',
    """export type CameraPlan = CameraSummary & {
  posiciones: Position[];
};
""",
    """export type CameraPlan = CameraSummary & {
  folios_sin_posicion: Folio[];
  posiciones: Position[];
};
""",
)
replace(
    'mobile/src/domain/estiba.ts',
    """  posicion_destino_id: string;
  sesion_destino_id: string;
""",
    """  camara_destino_id: string;
  posicion_destino_id?: string;
  sesion_destino_id: string;
""",
)

text = read('mobile/src/services/estibaApiDemo.ts')
text = text.replace(
    'ocupacion: { ocupadas: 0, total: 0, porcentaje: 0 },',
    'ocupacion: { ocupadas: 0, sin_posicion: 0, total: 0, porcentaje: 0 },',
)
text = text.replace(
    '    posiciones: createPositions(id, occupied),',
    '    folios_sin_posicion: [],\n    posiciones: createPositions(id, occupied),',
)
text = text.replace(
    '    ocupadas: occupied,\n    total,',
    '    ocupadas: occupied,\n    sin_posicion: plan.folios_sin_posicion.length,\n    total,',
)
text = text.replace(
    '    const destination = this.findPosition(payload.posicion_destino_id);',
    "    if (!payload.posicion_destino_id) {\n      throw new ApiError('La asignación solo a cámara no está disponible en modo demo.', 422);\n    }\n    const destination = this.findPosition(payload.posicion_destino_id);",
)
write('mobile/src/services/estibaApiDemo.ts', text)

replace(
    'mobile/src/components/ActionPanel.tsx',
    '  onLocate: () => void;\n',
    '  onLocate: () => void;\n  onAssignCamera: () => void;\n',
)
replace(
    'mobile/src/components/ActionPanel.tsx',
    '  onLocate,\n',
    '  onLocate,\n  onAssignCamera,\n',
)
replace(
    'mobile/src/components/ActionPanel.tsx',
    """        {plan.contenido === 'materiales' && canDispatchMaterial && (
""",
    """        {plan.contenido === 'materiales' && (
          <ActionButton
            compact={compact}
            disabled={busy || !canOperate}
            icon="▣"
            label="Asignar solo a cámara"
            onPress={onAssignCamera}
            subtitle="Disponible sin ocupar posición"
          />
        )}
        {plan.contenido === 'materiales' && canDispatchMaterial && (
""",
)

replace(
    'mobile/src/components/OperationModals.tsx',
    "  visible: boolean;\n};\n\nexport function LocateModal",
    "  visible: boolean;\n  cameraOnly?: boolean;\n};\n\nexport function LocateModal",
)
replace(
    'mobile/src/components/OperationModals.tsx',
    "  visible,\n}: LocateModalProps) {",
    "  visible,\n  cameraOnly = false,\n}: LocateModalProps) {",
)
replace(
    'mobile/src/components/OperationModals.tsx',
    """            eyebrow="UBICACIÓN INICIAL"
            onClose={onCancel}
            subtitle={'Destino: ' + (plan?.codigo ?? '') + ' · ' + (position?.etiqueta ?? '')}
            title="Registrar folio"
""",
    """            eyebrow={cameraOnly ? 'ASIGNACIÓN A CÁMARA' : 'UBICACIÓN INICIAL'}
            onClose={onCancel}
            subtitle={cameraOnly
              ? `Destino: ${plan?.codigo ?? ''} · Sin posición`
              : `Destino: ${plan?.codigo ?? ''} · ${position?.etiqueta ?? ''}`}
            title={cameraOnly ? 'Asignar material a cámara' : 'Registrar folio'}
""",
)
replace(
    'mobile/src/components/OperationModals.tsx',
    '            confirmLabel="Confirmar ubicación"\n',
    '            confirmLabel={cameraOnly ? "Asignar a cámara" : "Confirmar ubicación"}\n',
)

replace(
    'mobile/src/screens/OperationalScreen.tsx',
    '  const [locateVisible, setLocateVisible] = useState(false);\n',
    '  const [locateVisible, setLocateVisible] = useState(false);\n  const [locateCameraOnly, setLocateCameraOnly] = useState(false);\n  const [selectedCameraOnlyFolioId, setSelectedCameraOnlyFolioId] = useState<string | null>(null);\n',
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """  const operationalPosition = useMemo(() => {
    if (!selectedPosition) return null;
    const folio = selectedPosition.folios?.find((candidate) => candidate.id === selectedFolioId)
      ?? selectedPosition.folio;

    return { ...selectedPosition, folio };
  }, [selectedFolioId, selectedPosition]);
""",
    """  const operationalPosition = useMemo(() => {
    if (selectedPosition) {
      const folio = selectedPosition.folios?.find((candidate) => candidate.id === selectedFolioId)
        ?? selectedPosition.folio;

      return { ...selectedPosition, folio };
    }

    const folio = plan?.folios_sin_posicion?.find(
      (candidate) => candidate.id === selectedCameraOnlyFolioId,
    );
    if (!folio) return null;

    return {
      id: `camera-only:${folio.id}`,
      banda: 0,
      posicion: 0,
      nivel: 0,
      etiqueta: 'Sin posición',
      estado: 'activa' as const,
      ocupada: true,
      folio,
      folios: [folio],
    };
  }, [plan?.folios_sin_posicion, selectedCameraOnlyFolioId, selectedFolioId, selectedPosition]);
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    '    setSelectedFolioId(null);\n',
    '    setSelectedFolioId(null);\n    setSelectedCameraOnlyFolioId(null);\n',
)
regex(
    'mobile/src/screens/OperationalScreen.tsx',
    r"  async function openPositionFromMaterialDispatch\(cameraId: string, positionId: string\) \{.*?\n  \}\n",
    """  async function openPositionFromMaterialDispatch(
    cameraId: string,
    positionId: string | null,
    folioId: string,
  ) {
    setActiveModule('camaras');
    setSelectedCameraId(cameraId);
    setNotice('Folio abierto desde el despacho de materiales. Abre la estiba para registrar el retiro.');
    await loadCamera(cameraId);
    if (positionId) {
      setSelectedPositionId(positionId);
      setSelectedFolioId(folioId);
    } else {
      setSelectedPositionId(null);
      setSelectedCameraOnlyFolioId(folioId);
    }
  }
""",
)
regex(
    'mobile/src/screens/OperationalScreen.tsx',
    r"  async function confirmLocate\(form: LocateFormValue\) \{.*?\n  \}\n\n  async function openMove",
    """  async function confirmLocate(form: LocateFormValue) {
    if (!plan || !ownSession || (!locateCameraOnly && !selectedPosition)) return;
    setModalError('');
    const data = {
      condicion_sag_id: form.condicion_sag_id,
      variedad: form.variedad,
      calibre: form.calibre,
      marca: form.marca,
      exportadora: form.exportadora,
    };
    const compactData = Object.fromEntries(
      Object.entries(data).filter(([, value]) => Boolean(value)),
    ) as NonNullable<LocatePayload['datos_folio']>;
    const payload: LocatePayload = {
      operacion_id: Crypto.randomUUID(),
      numero_folio: form.numero_folio,
      tipo_bulto: form.tipo_bulto,
      camara_destino_id: plan.id,
      ...(!locateCameraOnly && selectedPosition
        ? { posicion_destino_id: selectedPosition.id }
        : {}),
      sesion_destino_id: ownSession.id,
      version_destino_conocida: plan.version_plano,
      generado_dispositivo_at: new Date().toISOString(),
      ...(!form.existente && Object.keys(compactData).length ? { datos_folio: compactData } : {}),
    };

    const succeeded = await runOperation(
      () => executeWithWarnings(payload, (confirmedPayload) => api.locate(auth.token, confirmedPayload)),
      setModalError,
    );

    if (succeeded) {
      setLocateVisible(false);
      setNotice(locateCameraOnly
        ? `Folio ${payload.numero_folio} asignado a ${plan.codigo} sin posición.`
        : `Folio ${payload.numero_folio} guardado en ${positionLabel(selectedPosition!)}.`);
      setLocateCameraOnly(false);
      await refreshCurrent();
    }
  }

  async function openMove""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    '    if (!plan || !operationalPosition?.folio || !ownSession) return;\n',
    "    if (!plan || !operationalPosition?.folio || !ownSession || operationalPosition.id.startsWith('camera-only:')) return;\n",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """            onOpenPosition={(cameraId, positionId) => (
              void openPositionFromMaterialDispatch(cameraId, positionId)
            )}
""",
    """            onOpenPosition={(cameraId, positionId, folioId) => (
              void openPositionFromMaterialDispatch(cameraId, positionId, folioId)
            )}
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """                onSelectPosition={(position) => {
                  setSelectedPositionId(position.id);
                  setSelectedFolioId(position.folio?.id ?? null);
                }}
""",
    """                onSelectPosition={(position) => {
                  setSelectedCameraOnlyFolioId(null);
                  setSelectedPositionId(position.id);
                  setSelectedFolioId(position.folio?.id ?? null);
                }}
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """            <View style={[styles.operationArea, !wideLayout && styles.operationAreaCompact]}>
              <PositionMap
""",
    """            <View style={[styles.operationArea, !wideLayout && styles.operationAreaCompact]}>
              <View style={styles.planColumn}>
              {plan.contenido === 'materiales' && plan.folios_sin_posicion.length > 0 ? (
                <View style={styles.cameraOnlyPanel}>
                  <Text style={styles.sectionEyebrow}>EN CÁMARA · SIN POSICIÓN</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator>
                    <View style={styles.cameraOnlyList}>
                      {plan.folios_sin_posicion.map((folio) => (
                        <Pressable
                          key={folio.id}
                          onPress={() => {
                            setSelectedPositionId(null);
                            setSelectedFolioId(folio.id);
                            setSelectedCameraOnlyFolioId(folio.id);
                          }}
                          style={[
                            styles.cameraOnlyItem,
                            selectedCameraOnlyFolioId === folio.id && styles.cameraOnlyItemActive,
                          ]}
                        >
                          <Text style={styles.cameraOnlyCode}>{folio.numero_folio}</Text>
                          <Text style={styles.cameraOnlyMeta}>
                            {folio.material?.item.codigo ?? 'Material'} · {folio.material?.cantidad_actual ?? '0'} {folio.material?.unidad_medida ?? ''}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                  </ScrollView>
                </View>
              ) : null}
              <PositionMap
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """                selectedPositionId={selectedPositionId}
              />
              <ActionPanel
""",
    """                selectedPositionId={selectedPositionId}
              />
              </View>
              <ActionPanel
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """                onLocate={() => {
                  setModalError('');
                  setNotice('');
                  setLocateVisible(true);
                }}
""",
    """                onLocate={() => {
                  setModalError('');
                  setNotice('');
                  setLocateCameraOnly(false);
                  setLocateVisible(true);
                }}
                onAssignCamera={() => {
                  setModalError('');
                  setNotice('');
                  setLocateCameraOnly(true);
                  setLocateVisible(true);
                }}
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """      <LocateModal
        busy={busy}
""",
    """      <LocateModal
        busy={busy}
        cameraOnly={locateCameraOnly}
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    """          setModalError('');
          setLocateVisible(false);
        }}
""",
    """          setModalError('');
          setLocateVisible(false);
          setLocateCameraOnly(false);
        }}
""",
)
replace(
    'mobile/src/screens/OperationalScreen.tsx',
    "  operationArea: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'flex-start', gap: 12 },\n",
    """  operationArea: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  planColumn: { flex: 1, minWidth: 0, gap: 10 },
  cameraOnlyPanel: {
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
  },
  cameraOnlyList: { flexDirection: 'row', gap: 8, marginTop: 7 },
  cameraOnlyItem: {
    minWidth: 160,
    padding: 9,
    borderRadius: 9,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.backgroundDeep,
  },
  cameraOnlyItemActive: { borderColor: colors.cyan, backgroundColor: colors.selected },
  cameraOnlyCode: { color: colors.text, fontSize: 9, fontWeight: '900' },
  cameraOnlyMeta: { marginTop: 3, color: colors.muted, fontSize: 8 },
""",
)

replace(
    'mobile/src/components/MaterialDispatchOperation.tsx',
    '  onOpenPosition: (cameraId: string, positionId: string) => void;\n',
    '  onOpenPosition: (cameraId: string, positionId: string | null, folioId: string) => void;\n',
)
replace(
    'mobile/src/components/MaterialDispatchOperation.tsx',
    '                          disabled={!suggestion.camara || !suggestion.posicion}\n',
    '                          disabled={!suggestion.camara}\n',
)
replace(
    'mobile/src/components/MaterialDispatchOperation.tsx',
    """                            if (suggestion.camara && suggestion.posicion) {
                              onOpenPosition(suggestion.camara.id, suggestion.posicion.id);
                            }
""",
    """                            if (suggestion.camara) {
                              onOpenPosition(
                                suggestion.camara.id,
                                suggestion.posicion?.id ?? null,
                                suggestion.folio_id,
                              );
                            }
""",
)
replace(
    'mobile/src/components/MaterialDispatchOperation.tsx',
    """                              {suggestion.camara && suggestion.posicion
                                ? `${suggestion.camara.codigo} · ${suggestion.posicion.etiqueta}`
                                : 'Sin ubicación operable'}
""",
    """                              {suggestion.camara
                                ? `${suggestion.camara.codigo} · ${suggestion.posicion?.etiqueta ?? 'Sin posición'}`
                                : 'Sin cámara asignada'}
""",
)

print('Bloque 4 aplicado')
