import * as Crypto from 'expo-crypto';
import { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';

import { AuthSession } from '../domain/estiba';
import { ContainerType, MpCatalog, MpReception, MpSegmentDraft, SegregationReason } from '../domain/validationMp';
import { confirmMpValidation, findMpReception, getMpCatalog, listPendingMp, takeMpReception } from '../services/validationMpApi';
import { colors } from '../theme/colors';

type Props = { auth: AuthSession; baseUrl: string; onLogout: () => void };
const types: ContainerType[] = ['bins', 'totes', 'esponjas'];

export function ValidationMpScreen({ auth, baseUrl, onLogout }: Props) {
  const [pending, setPending] = useState<MpReception[]>([]);
  const [number, setNumber] = useState('');
  const [reception, setReception] = useState<MpReception | null>(null);
  const [catalog, setCatalog] = useState<MpCatalog | null>(null);
  const [validationId, setValidationId] = useState<string | null>(null);
  const [quantities, setQuantities] = useState<Record<ContainerType, string>>(emptyQuantities());
  const [tagsChecked, setTagsChecked] = useState(false);
  const [segregation, setSegregation] = useState(false);
  const [segments, setSegments] = useState<MpSegmentDraft[]>([]);
  const [observation, setObservation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const isFruit = reception?.tipo_recepcion === 'fruta_con_envases';

  async function refresh() {
    setBusy(true); setError('');
    try { setPending(await listPendingMp(baseUrl, auth.token)); }
    catch (reason) { setError(message(reason)); }
    finally { setBusy(false); }
  }
  useEffect(() => { void refresh(); }, []);

  async function open(receptionNumber: string) {
    const normalized = receptionNumber.trim().toUpperCase();
    if (!normalized) return;
    setBusy(true); setError('');
    try {
      const loaded = await findMpReception(baseUrl, auth.token, normalized);
      setReception(loaded); setNumber(loaded.numero_recepcion);
      setValidationId(loaded.validacion?.estado === 'en_curso' ? loaded.validacion.id : null);
      setQuantities(Object.fromEntries(types.map((type) => [type, String(loaded.envases.find((x) => x.tipo_envase === type)?.cantidad_declarada ?? 0)])) as Record<ContainerType, string>);
      setCatalog(await getMpCatalog(baseUrl, auth.token, loaded.id));
      setTagsChecked(false); setSegregation(false); setSegments([]); setObservation('');
    } catch (reason) { setError(message(reason)); }
    finally { setBusy(false); }
  }

  async function take() {
    if (!reception) return;
    setBusy(true); setError('');
    try { const validation = await takeMpReception(baseUrl, auth.token, reception.id); setValidationId(validation.id); await refresh(); }
    catch (reason) { setError(message(reason)); }
    finally { setBusy(false); }
  }

  const actualContainers = useMemo(() => reception?.envases.map((item) => ({
    tipo_envase: item.tipo_envase,
    cantidad_validada: Number(quantities[item.tipo_envase] || 0),
  })) ?? [], [quantities, reception]);

  async function confirm() {
    if (!validationId || !reception) return;
    if (isFruit && !tagsChecked) { setError('Debes confirmar el chequeo visual de las tarjas de campo.'); return; }
    if (segregation && segments.length < 2) { setError('Agrega al menos dos segmentos para segregar.'); return; }
    setBusy(true); setError('');
    try {
      const result = await confirmMpValidation(baseUrl, auth.token, validationId, { containers: actualContainers, tagsChecked, segregation, segments, observation });
      Alert.alert('Recepción validada', `${result.numero_recepcion} quedó lista${result.segmentos.length ? ` con ${result.segmentos.length} segmento(s) pendiente(s) de lote` : ''}.`);
      setReception(null); setValidationId(null); setNumber(''); await refresh();
    } catch (reason) { setError(message(reason)); }
    finally { setBusy(false); }
  }

  function addSegment() { setSegments((current) => [...current, { key: Crypto.randomUUID(), motivos: [], csg_validacion_id: null, cuartel: '', variedad_validacion_id: null, cantidades: emptyQuantities() }]); }
  function updateSegment(key: string, patch: Partial<MpSegmentDraft>) { setSegments((current) => current.map((segment) => segment.key === key ? { ...segment, ...patch } : segment)); }
  function toggleReason(segment: MpSegmentDraft, reason: SegregationReason) { updateSegment(segment.key, { motivos: segment.motivos.includes(reason) ? segment.motivos.filter((item) => item !== reason) : [...segment.motivos, reason] }); }

  return <View style={styles.page}>
    <View style={styles.header}><View><Text style={styles.eyebrow}>VALIDACIÓN MP · RECEPCIÓN</Text><Text style={styles.title}>Control de carga en piso</Text></View><View style={styles.headerActions}><Pressable onPress={() => void refresh()} style={styles.secondary}><Text style={styles.secondaryText}>↻ Pendientes</Text></Pressable><Pressable onPress={onLogout} style={styles.secondary}><Text style={styles.secondaryText}>Salir</Text></Pressable></View></View>
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <View style={styles.searchCard}><Text style={styles.cardTitle}>Ingresar correlativo de Romana</Text><Text style={styles.muted}>Escribe o pistolea el REC-…; la información se completa automáticamente.</Text><View style={styles.searchRow}><TextInput autoCapitalize="characters" autoFocus onChangeText={setNumber} onSubmitEditing={() => void open(number)} placeholder="REC-2607-0001" placeholderTextColor={colors.muted} style={styles.searchInput} value={number}/><Pressable onPress={() => void open(number)} style={styles.primary}><Text style={styles.primaryText}>Buscar</Text></Pressable></View></View>
      {error ? <Text style={styles.error}>{error}</Text> : null}{busy ? <ActivityIndicator color={colors.cyan}/> : null}
      {!reception ? <PendingList items={pending} onOpen={(item) => void open(item.numero_recepcion)}/> : <>
        <View style={styles.receiptCard}><View style={styles.receiptHeading}><View><Text style={styles.eyebrow}>{reception.tipo_recepcion === 'solo_envases' ? 'SOLO ENVASES' : 'FRUTA CON ENVASES'}</Text><Text style={styles.receiptNumber}>{reception.numero_recepcion}</Text></View><Text style={styles.status}>{label(reception.estado_validacion_mp)}</Text></View><Fact label="Cliente" value={reception.cliente.nombre}/><Fact label="Guía" value={reception.numero_guia_despacho}/><Fact label="Camión" value={`${reception.patente_camion} · ${reception.conductor.nombre}`}/><Fact label="Temporada heredada" value={`${reception.temporada.codigo} · ${reception.temporada.nombre}`}/><Fact label="Ingreso exacto" value={formatDate(reception.ingreso_at)}/></View>
        {!validationId ? <Pressable disabled={reception.estado_validacion_mp !== 'pendiente'} onPress={() => void take()} style={[styles.primary, reception.estado_validacion_mp !== 'pendiente' && styles.disabled]}><Text style={styles.primaryText}>Tomar recepción para validar</Text></Pressable> : <View style={styles.formCard}>
          <Text style={styles.cardTitle}>Conteo real en piso</Text><Text style={styles.muted}>La diferencia se registra y no bloquea la validación.</Text>
          {reception.envases.map((item) => { const actual = Number(quantities[item.tipo_envase] || 0); const difference = actual - item.cantidad_declarada; return <View key={item.tipo_envase} style={styles.countRow}><View><Text style={styles.countType}>{label(item.tipo_envase)}</Text><Text style={styles.muted}>Guía: {item.cantidad_declarada} · Diferencia: {difference > 0 ? '+' : ''}{difference}</Text></View><TextInput keyboardType="number-pad" onChangeText={(value) => setQuantities((current) => ({ ...current, [item.tipo_envase]: value.replace(/\D/g, '') }))} style={styles.countInput} value={quantities[item.tipo_envase]}/></View>})}
          {isFruit ? <><Check active={tagsChecked} label="Tarjas de campo verificadas visualmente" onPress={() => setTagsChecked((value) => !value)}/><View style={styles.choiceRow}><Text style={styles.countType}>¿Requiere segregación?</Text><Chip active={!segregation} label="No" onPress={() => { setSegregation(false); setSegments([]); }}/><Chip active={segregation} label="Sí" onPress={() => { setSegregation(true); if (!segments.length) { addSegment(); addSegment(); } }}/></View>{segregation ? <View style={styles.segments}><View style={styles.segmentHeading}><Text style={styles.cardTitle}>Segmentos futuros</Text><Pressable onPress={addSegment} style={styles.secondary}><Text style={styles.secondaryText}>+ Segmento</Text></Pressable></View>{segments.map((segment, index) => <SegmentEditor catalog={catalog} containers={actualContainers.map((item) => item.tipo_envase)} index={index} key={segment.key} onChange={(patch) => updateSegment(segment.key, patch)} onRemove={() => setSegments((current) => current.filter((item) => item.key !== segment.key))} onToggleReason={(reason) => toggleReason(segment, reason)} segment={segment}/>)}</View> : null}</> : <Text style={styles.info}>Recepción solo de envases: no requiere tarjas ni segregación.</Text>}
          <TextInput multiline onChangeText={setObservation} placeholder="Observación opcional" placeholderTextColor={colors.muted} style={styles.observation} value={observation}/><Pressable onPress={() => void confirm()} style={styles.primary}><Text style={styles.primaryText}>Confirmar Validación MP</Text></Pressable>
        </View>}
      </>}
    </ScrollView>
  </View>;
}

function PendingList({ items, onOpen }: { items: MpReception[]; onOpen: (item: MpReception) => void }) { return <View style={styles.pending}><View style={styles.pendingTitle}><Text style={styles.cardTitle}>Recepciones en verde</Text><Text style={styles.status}>{items.length} pendientes</Text></View>{items.length ? items.map((item) => <Pressable key={item.id} onPress={() => onOpen(item)} style={styles.pendingItem}><View><Text style={styles.pendingNumber}>{item.numero_recepcion}</Text><Text style={styles.muted}>{item.cliente.nombre} · guía {item.numero_guia_despacho}</Text><Text style={styles.muted}>{item.envases.map((x) => `${x.cantidad_declarada} ${label(x.tipo_envase)}`).join(' · ')}</Text></View><Text style={styles.openArrow}>→</Text></Pressable>) : <Text style={styles.muted}>No hay recepciones pendientes en este momento.</Text>}</View>; }
function SegmentEditor({ catalog, containers, index, onChange, onRemove, onToggleReason, segment }: { catalog: MpCatalog | null; containers: ContainerType[]; index: number; onChange: (patch: Partial<MpSegmentDraft>) => void; onRemove: () => void; onToggleReason: (reason: SegregationReason) => void; segment: MpSegmentDraft }) { return <View style={styles.segmentCard}><View style={styles.segmentHeading}><Text style={styles.countType}>Segmento {index + 1}</Text><Pressable onPress={onRemove}><Text style={styles.remove}>Eliminar</Text></Pressable></View><Text style={styles.muted}>Motivo(s)</Text><View style={styles.chips}>{(['csg','cuartel','variedad'] as SegregationReason[]).map((reason) => <Chip active={segment.motivos.includes(reason)} key={reason} label={label(reason)} onPress={() => onToggleReason(reason)}/>)}</View>{segment.motivos.includes('csg') ? <><Text style={styles.muted}>CSG</Text><ScrollView horizontal>{catalog?.csg.map((item) => <Chip active={segment.csg_validacion_id === item.id} key={item.id} label={item.codigo} onPress={() => onChange({ csg_validacion_id: item.id })}/>)}</ScrollView></> : null}{segment.motivos.includes('cuartel') ? <TextInput onChangeText={(cuartel) => onChange({ cuartel })} placeholder="Cuartel" placeholderTextColor={colors.muted} style={styles.segmentInput} value={segment.cuartel}/> : null}{segment.motivos.includes('variedad') ? <><Text style={styles.muted}>Variedad</Text><ScrollView horizontal>{catalog?.variedades.map((item) => <Chip active={segment.variedad_validacion_id === item.id} key={item.id} label={item.nombre} onPress={() => onChange({ variedad_validacion_id: item.id })}/>)}</ScrollView></> : null}<View style={styles.segmentCounts}>{containers.map((type) => <View key={type}><Text style={styles.muted}>{label(type)}</Text><TextInput keyboardType="number-pad" onChangeText={(value) => onChange({ cantidades: { ...segment.cantidades, [type]: value.replace(/\D/g, '') } })} style={styles.segmentCount} value={segment.cantidades[type]}/></View>)}</View></View>; }
function Fact({ label: factLabel, value }: { label: string; value: string }) { return <View style={styles.fact}><Text style={styles.muted}>{factLabel}</Text><Text style={styles.factValue}>{value}</Text></View>; }function Chip({ active, label: chipLabel, onPress }: { active: boolean; label: string; onPress: () => void }) { return <Pressable onPress={onPress} style={[styles.chip, active && styles.chipActive]}><Text style={[styles.chipText, active && styles.chipTextActive]}>{chipLabel}</Text></Pressable>; }function Check({ active, label: checkLabel, onPress }: { active: boolean; label: string; onPress: () => void }) { return <Pressable onPress={onPress} style={styles.check}><Text style={[styles.checkBox, active && styles.checkBoxActive]}>{active ? '✓' : ''}</Text><Text style={styles.factValue}>{checkLabel}</Text></Pressable>; }
function emptyQuantities(): Record<ContainerType,string> { return { bins:'0',totes:'0',esponjas:'0' }; }function label(value: string) { return value.replaceAll('_',' ').replace(/^./,(c)=>c.toUpperCase()); }function formatDate(value: string) { return new Intl.DateTimeFormat('es-CL',{dateStyle:'short',timeStyle:'short'}).format(new Date(value)); }function message(reason: unknown) { return reason instanceof Error ? reason.message : 'No fue posible completar la operación.'; }

const styles=StyleSheet.create({page:{flex:1,backgroundColor:colors.background},header:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',padding:16,borderBottomWidth:1,borderBottomColor:colors.border,backgroundColor:colors.backgroundDeep},headerActions:{flexDirection:'row',gap:8},eyebrow:{color:colors.cyan,fontSize:10,fontWeight:'900',letterSpacing:1.2},title:{color:colors.text,fontSize:20,fontWeight:'900',marginTop:3},content:{padding:14,gap:12},searchCard:{padding:14,borderRadius:14,borderWidth:1,borderColor:colors.cyanDark,backgroundColor:colors.panel},cardTitle:{color:colors.text,fontSize:16,fontWeight:'900'},muted:{color:colors.muted,fontSize:12,marginTop:3},searchRow:{flexDirection:'row',gap:8,marginTop:12},searchInput:{flex:1,borderWidth:1,borderColor:colors.border,borderRadius:10,padding:12,color:colors.text,fontSize:17,fontWeight:'900',backgroundColor:colors.backgroundDeep},primary:{alignItems:'center',justifyContent:'center',paddingHorizontal:18,paddingVertical:12,borderRadius:10,backgroundColor:colors.cyan},primaryText:{color:colors.accentText,fontWeight:'900'},secondary:{paddingHorizontal:12,paddingVertical:8,borderRadius:9,borderWidth:1,borderColor:colors.cyanDark},secondaryText:{color:colors.cyan,fontWeight:'900',fontSize:11},error:{color:colors.red,fontWeight:'800',padding:10,borderRadius:8,backgroundColor:'#32161a'},pending:{padding:14,borderRadius:14,borderWidth:1,borderColor:colors.border,backgroundColor:colors.panel,gap:8},pendingTitle:{flexDirection:'row',justifyContent:'space-between',alignItems:'center'},pendingItem:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',padding:12,borderRadius:10,borderWidth:1,borderColor:colors.border,backgroundColor:colors.backgroundDeep},pendingNumber:{color:colors.text,fontSize:15,fontWeight:'900'},openArrow:{color:colors.cyan,fontSize:24},receiptCard:{padding:14,borderRadius:14,borderWidth:1,borderColor:colors.border,backgroundColor:colors.panel},receiptHeading:{flexDirection:'row',justifyContent:'space-between',alignItems:'center',marginBottom:10},receiptNumber:{color:colors.text,fontSize:24,fontWeight:'900'},status:{color:colors.cyan,fontSize:11,fontWeight:'900',textTransform:'uppercase'},fact:{paddingVertical:8,borderBottomWidth:1,borderBottomColor:colors.border},factValue:{color:colors.text,fontWeight:'800',marginTop:3},formCard:{padding:14,borderRadius:14,borderWidth:1,borderColor:colors.border,backgroundColor:colors.panel,gap:10},countRow:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',padding:10,borderRadius:10,backgroundColor:colors.backgroundDeep},countType:{color:colors.text,fontWeight:'900'},countInput:{width:90,textAlign:'center',borderWidth:1,borderColor:colors.cyanDark,borderRadius:9,padding:10,color:colors.text,fontSize:18,fontWeight:'900'},check:{flexDirection:'row',alignItems:'center',gap:10,paddingVertical:8},checkBox:{width:25,height:25,borderWidth:1,borderColor:colors.cyanDark,borderRadius:6,textAlign:'center',color:colors.accentText,fontWeight:'900',paddingTop:2},checkBoxActive:{backgroundColor:colors.cyan},choiceRow:{flexDirection:'row',alignItems:'center',gap:8},chip:{paddingHorizontal:11,paddingVertical:8,borderRadius:20,borderWidth:1,borderColor:colors.border,marginRight:7,marginTop:6},chipActive:{backgroundColor:colors.cyan,borderColor:colors.cyan},chipText:{color:colors.muted,fontWeight:'800'},chipTextActive:{color:colors.accentText},chips:{flexDirection:'row',flexWrap:'wrap'},segments:{gap:10},segmentHeading:{flexDirection:'row',alignItems:'center',justifyContent:'space-between'},segmentCard:{padding:12,borderRadius:12,borderWidth:1,borderColor:colors.cyanDark,backgroundColor:colors.backgroundDeep,gap:8},remove:{color:colors.red,fontWeight:'800'},segmentInput:{borderWidth:1,borderColor:colors.border,borderRadius:9,padding:10,color:colors.text},segmentCounts:{flexDirection:'row',gap:8},segmentCount:{width:80,borderWidth:1,borderColor:colors.border,borderRadius:8,padding:8,color:colors.text,textAlign:'center'},observation:{minHeight:70,textAlignVertical:'top',borderWidth:1,borderColor:colors.border,borderRadius:9,padding:10,color:colors.text},info:{color:colors.cyan,padding:10,borderRadius:8,backgroundColor:'#07303a'},disabled:{opacity:.45}});
