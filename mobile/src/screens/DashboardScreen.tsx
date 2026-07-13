import { useMemo, useState } from 'react';
import {
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';

import { ActionPanel } from '../components/ActionPanel';
import { CameraCard } from '../components/CameraCard';
import { PositionMap } from '../components/PositionMap';
import { RecentMovements } from '../components/RecentMovements';
import { activeSession, cameras, recentMovements } from '../data/dashboardMock';
import { Position } from '../domain/dashboard';
import { colors } from '../theme/colors';

export function DashboardScreen() {
  const { width } = useWindowDimensions();
  const wideLayout = width >= 1180;
  const narrowLayout = width < 880;
  const [selectedCameraId, setSelectedCameraId] = useState('camara-03');
  const [selectedPositionId, setSelectedPositionId] = useState<string | null>('0-01-06');

  const selectedCamera = useMemo(
    () => cameras.find((camera) => camera.id === selectedCameraId) ?? cameras[0],
    [selectedCameraId],
  );

  const selectedPosition = useMemo(
    () => selectedCamera.positions.find((position) => position.id === selectedPositionId) ?? null,
    [selectedCamera, selectedPositionId],
  );

  const readOnly = selectedCamera.status === 'editing';

  function selectCamera(cameraId: string) {
    setSelectedCameraId(cameraId);
    setSelectedPositionId(null);
  }

  function selectPosition(position: Position) {
    setSelectedPositionId(position.id);
  }

  function showPendingFeature(feature: 'scan' | 'move') {
    if (readOnly) {
      Alert.alert(
        'Cámara en modo consulta',
        `${selectedCamera.editor} está editando esta cámara. Puedes revisar el plano, pero no modificarlo.`,
      );
      return;
    }

    if (feature === 'move' && !selectedPosition) {
      Alert.alert('Selecciona una posición', 'Toca una posición del mapa antes de iniciar el movimiento.');
      return;
    }

    Alert.alert(
      feature === 'scan' ? 'Escáner pendiente' : 'Movimiento pendiente',
      'La interacción visual está lista. La conectaremos con la API en un próximo PR.',
    );
  }

  const cameraCards = cameras.map((camera) => (
    <CameraCard
      camera={camera}
      key={camera.id}
      onPress={() => selectCamera(camera.id)}
      selected={camera.id === selectedCamera.id}
    />
  ));

  return (
    <ScrollView contentContainerStyle={styles.page}>
      <View style={styles.header}>
        <View style={styles.brand}>
          <Text style={styles.snowflake}>❄</Text>
          <View>
            <Text style={styles.brandName}>Estiba WMS</Text>
            <Text style={styles.version}>PROTOTIPO MÓVIL</Text>
          </View>
        </View>

        <View style={[styles.notice, readOnly ? styles.noticeWarning : styles.noticeAvailable]}>
          <Text style={styles.noticeIcon}>{readOnly ? '!' : '✓'}</Text>
          <View style={styles.noticeCopy}>
            <Text style={[styles.noticeTitle, !readOnly && styles.noticeTitleAvailable]}>
              {readOnly ? `Cámara editada por ${selectedCamera.editor}` : 'Cámara disponible para edición'}
            </Text>
            <Text style={styles.noticeSubtitle}>{readOnly ? 'Solo lectura' : selectedCamera.name}</Text>
          </View>
        </View>

        <View style={styles.sync}>
          <Text style={styles.syncIcon}>⌁</Text>
          <View>
            <Text style={styles.syncText}>Sincronizado</Text>
            <Text style={styles.syncTime}>Ahora · 10:24</Text>
          </View>
        </View>
      </View>

      <View style={[styles.body, !wideLayout && styles.bodyCompact]}>
        {wideLayout ? (
          <View style={styles.cameraRail}>{cameraCards}</View>
        ) : (
          <ScrollView
            contentContainerStyle={styles.cameraRailHorizontal}
            horizontal
            showsHorizontalScrollIndicator={false}
          >
            {cameraCards}
          </ScrollView>
        )}

        <View style={[styles.workspace, narrowLayout && styles.workspaceNarrow]}>
          <PositionMap
            camera={selectedCamera}
            onSelectPosition={selectPosition}
            selectedPositionId={selectedPositionId}
          />
          <ActionPanel
            onMove={() => showPendingFeature('move')}
            onScan={() => showPendingFeature('scan')}
            readOnly={readOnly}
            selectedPosition={selectedPosition}
            session={activeSession}
          />
        </View>
      </View>

      <RecentMovements movements={recentMovements} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: {
    minHeight: '100%',
    padding: 14,
    backgroundColor: colors.background,
  },
  header: {
    marginBottom: 14,
    flexDirection: 'row',
    alignItems: 'stretch',
    gap: 14,
  },
  brand: {
    minWidth: 220,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  snowflake: {
    color: colors.cyan,
    fontSize: 45,
  },
  brandName: {
    color: colors.text,
    fontSize: 24,
    fontWeight: '900',
  },
  version: {
    marginTop: 2,
    color: colors.cyan,
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.3,
  },
  notice: {
    flex: 1,
    minHeight: 62,
    paddingHorizontal: 18,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  noticeWarning: {
    borderColor: colors.amber,
    backgroundColor: colors.amberDark,
  },
  noticeAvailable: {
    borderColor: colors.green,
    backgroundColor: '#113C2A',
  },
  noticeIcon: {
    color: colors.text,
    fontSize: 27,
    fontWeight: '900',
  },
  noticeCopy: {
    flex: 1,
  },
  noticeTitle: {
    color: colors.amber,
    fontSize: 17,
    fontWeight: '900',
  },
  noticeTitleAvailable: {
    color: colors.green,
  },
  noticeSubtitle: {
    marginTop: 2,
    color: colors.text,
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  sync: {
    minWidth: 190,
    paddingHorizontal: 16,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.panel,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  syncIcon: {
    color: colors.green,
    fontSize: 31,
    fontWeight: '900',
  },
  syncText: {
    color: colors.green,
    fontSize: 13,
    fontWeight: '800',
  },
  syncTime: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 10,
  },
  body: {
    flexDirection: 'row',
    alignItems: 'stretch',
    gap: 14,
  },
  bodyCompact: {
    flexDirection: 'column',
  },
  cameraRail: {
    width: 250,
    gap: 12,
  },
  cameraRailHorizontal: {
    gap: 12,
    paddingBottom: 2,
  },
  workspace: {
    minWidth: 0,
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 14,
  },
  workspaceNarrow: {
    flexDirection: 'column',
  },
});
