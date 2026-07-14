import { Pressable, StyleSheet, Text, View } from 'react-native';

import { CameraPlan, Position } from '../domain/estiba';
import { colors } from '../theme/colors';

type ActionPanelProps = {
  busy: boolean;
  canOperate: boolean;
  plan: CameraPlan;
  selectedPosition: Position | null;
  onLocate: () => void;
  onMove: () => void;
  onRefresh: () => void;
  onToggleSession: () => void;
};

export function ActionPanel({
  busy,
  canOperate,
  onLocate,
  onMove,
  onRefresh,
  onToggleSession,
  plan,
  selectedPosition,
}: ActionPanelProps) {
  const editing = plan.acceso.modo === 'edicion';
  const readOnly = plan.acceso.modo === 'solo_lectura';
  const locateDisabled = busy || !canOperate || !selectedPosition || selectedPosition.ocupada || selectedPosition.estado !== 'activa';
  const moveDisabled = busy || !canOperate || !selectedPosition?.ocupada || selectedPosition.estado !== 'activa';

  return (
    <View style={styles.panel}>
      <View>
        <Text style={styles.eyebrow}>OPERACIÓN</Text>
        <Text style={styles.title}>Acciones rápidas</Text>
      </View>

      <View style={styles.selection}>
        <Text style={styles.selectionLabel}>POSICIÓN SELECCIONADA</Text>
        <Text style={styles.selectionValue}>{selectedPosition?.etiqueta ?? 'Ninguna'}</Text>
        <Text style={styles.selectionDetail}>
          {!selectedPosition
            ? 'Toca una posición del plano'
            : selectedPosition.estado !== 'activa'
              ? 'Posición no disponible'
              : selectedPosition.ocupada ? 'Ocupada por un folio' : 'Libre para ubicación'}
        </Text>
      </View>

      {selectedPosition?.folio && (
        <View style={styles.folioCard}>
          <View style={styles.folioTop}>
            <Text style={styles.folioType}>{selectedPosition.folio.tipo_bulto.toUpperCase()}</Text>
            <View style={styles.folioDot} />
          </View>
          <Text style={styles.folioNumber}>{selectedPosition.folio.numero_folio}</Text>
          <Detail label="Variedad" value={selectedPosition.folio.variedad} />
          <Detail label="Calibre" value={selectedPosition.folio.calibre} />
          <Detail label="Condición" value={selectedPosition.folio.condicion_sag?.codigo} />
        </View>
      )}

      <View style={styles.actions}>
        <ActionButton
          disabled={locateDisabled}
          icon="＋"
          label="Ubicar folio"
          onPress={onLocate}
          primary
          subtitle="Registrar un bulto nuevo"
        />
        <ActionButton
          disabled={moveDisabled}
          icon="⇄"
          label="Mover folio"
          onPress={onMove}
          subtitle="Reubicar o cambiar cámara"
        />
        <ActionButton
          disabled={busy || readOnly}
          icon="⌁"
          label={editing ? 'Cerrar estiba' : readOnly ? 'Cámara en uso' : 'Abrir estiba'}
          onPress={onToggleSession}
          subtitle={editing ? 'Liberar la cámara' : readOnly ? 'Edición bloqueada' : 'Iniciar sesión de edición'}
        />
        <ActionButton
          disabled={busy}
          icon="↻"
          label="Actualizar plano"
          onPress={onRefresh}
          subtitle="Traer cambios del servidor"
        />
      </View>

      <Text style={styles.note}>
        {readOnly
          ? 'Puedes consultar el plano. La edición se habilitará cuando se cierre la otra sesión.'
          : editing
            ? selectedPosition ? 'Confirma cada movimiento antes de continuar.' : 'Sesión activa: selecciona una posición.'
            : 'Abre la estiba para habilitar movimientos.'}
      </Text>
    </View>
  );
}

function Detail({ label, value }: { label: string; value?: string | null }) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value || '—'}</Text>
    </View>
  );
}

type ActionButtonProps = {
  disabled: boolean;
  icon: string;
  label: string;
  onPress: () => void;
  primary?: boolean;
  subtitle: string;
};

function ActionButton({ disabled, icon, label, onPress, primary, subtitle }: ActionButtonProps) {
  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [
        styles.actionButton,
        primary && styles.actionPrimary,
        disabled && styles.actionDisabled,
        pressed && styles.actionPressed,
      ]}
    >
      <Text style={[styles.actionIcon, primary && styles.actionPrimaryText]}>{icon}</Text>
      <View style={styles.actionCopy}>
        <Text style={[styles.actionLabel, primary && styles.actionPrimaryText]}>{label}</Text>
        <Text style={[styles.actionSubtitle, primary && styles.actionPrimarySubtitle]}>{subtitle}</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  panel: {
    width: 255,
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    gap: 12,
  },
  eyebrow: { color: colors.cyan, fontSize: 9, fontWeight: '900', letterSpacing: 1.2 },
  title: { marginTop: 3, color: colors.text, fontSize: 18, fontWeight: '900' },
  selection: {
    padding: 12,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  selectionLabel: { color: colors.muted, fontSize: 8, fontWeight: '900' },
  selectionValue: { marginTop: 5, color: colors.text, fontSize: 18, fontWeight: '900' },
  selectionDetail: { marginTop: 2, color: colors.muted, fontSize: 9 },
  folioCard: {
    padding: 12,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.cyanDark,
    backgroundColor: colors.panelStrong,
  },
  folioTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  folioType: { color: colors.cyan, fontSize: 8, fontWeight: '900' },
  folioDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.cyan },
  folioNumber: { marginVertical: 8, color: colors.text, fontSize: 18, fontWeight: '900' },
  detailRow: { marginTop: 3, flexDirection: 'row', justifyContent: 'space-between', gap: 8 },
  detailLabel: { color: colors.muted, fontSize: 8 },
  detailValue: { color: colors.text, fontSize: 8, fontWeight: '800' },
  actions: { gap: 8 },
  actionButton: {
    minHeight: 54,
    paddingHorizontal: 11,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panelStrong,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  actionPrimary: { borderColor: colors.cyan, backgroundColor: colors.cyan },
  actionDisabled: { opacity: 0.38 },
  actionPressed: { opacity: 0.72 },
  actionIcon: { width: 28, color: colors.cyan, fontSize: 25, textAlign: 'center' },
  actionCopy: { flex: 1 },
  actionLabel: { color: colors.text, fontSize: 11, fontWeight: '900' },
  actionSubtitle: { marginTop: 2, color: colors.muted, fontSize: 8 },
  actionPrimaryText: { color: '#032022' },
  actionPrimarySubtitle: { color: '#10585A' },
  note: { color: colors.muted, fontSize: 8, lineHeight: 12 },
});
