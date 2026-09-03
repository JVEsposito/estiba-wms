# Planes operacionales de Frigorífico

Este incremento crea la base auditable para coordinar movimientos de pallets completos sin modificar todavía la operación vigente de cámaras.

## Alcance inicial

- Un plan representa un objetivo operacional: recepción de túnel, almacenamiento, concentración de carga, preparación de inspección, segregación, movimiento de oportunidad, reordenamiento, desocupación, evacuación, despacho directo o corrección.
- Cada tarea indica un movimiento físico concreto, su orden, prioridad, pallet, origen y destino propuesto.
- Solo se admiten folios activos de tipo `pallet` pertenecientes a la temporada activa. Los saldos y materiales quedan deliberadamente fuera de este planificador.
- Una tarea puede ser asumida por una sola combinación de usuario y dispositivo. Repetir la misma toma es idempotente; otro camarero recibe un conflicto operacional.
- Los movimientos existentes pueden quedar sin plan ni tarea. Las nuevas relaciones son opcionales para preservar compatibilidad y permitir una adopción gradual.

## Bandas operacionales

Cada banda vigente de una cámara de producto terminado dispone de una identidad operacional independiente de sus posiciones. Expone:

- usos permitidos: `transito_pt`, `inspeccion` y `retenidos`;
- modo explícito: `operativa`, `bloqueada` o `en_vaciado`;
- capacidad física y efectiva, ocupadas y disponibles;
- estado calculado: `libre`, `parcial`, `completa`, `bloqueada` o `en_vaciado`;
- versión y responsable de la última configuración.

El plano web y la tablet muestran este resumen sobre cada banda. La configuración de la banda incrementa la versión del plano para invalidar sus ETag.

## Afinidad y ubicación recomendada

La afinidad no se configura manualmente ni se persiste como una reserva. Se deriva de los pallets completos que ocupan actualmente cada banda y se libera cuando la banda queda vacía.

La jerarquía de recomendación es:

1. mismo cliente, marca/etiqueta y formato/envase;
2. mismo cliente y marca/etiqueta;
3. mismo cliente;
4. banda libre;
5. mezcla excepcional como última alternativa.

Solo participan bandas operativas, habilitadas para `transito_pt`, con capacidad disponible y sin saldos ni materiales. La posición propuesta respeta el orden desde el fondo y el soporte del nivel inferior. La respuesta conserva la versión de cámara y banda, explica el criterio aplicado y expone hasta cuatro alternativas.

La consulta previa del folio entrega esta recomendación a web y tablet/PDA. La recomendación sigue siendo consultiva: la reserva nace únicamente cuando el camarero asume una tarea operacional que ya posee un destino concreto.

## Reservas durante la ejecución

Al asumir una tarea se crea un lease auditable para la combinación tarea, camarero y dispositivo. Si la tarea posee una posición de destino, el mismo registro bloquea además esa posición mediante una restricción única en base de datos.

- El lease dura 10 minutos de forma predeterminada y puede configurarse con `WMS_RESERVA_TAREA_MINUTOS`.
- Repetir la toma desde el mismo usuario y dispositivo renueva el lease sin duplicar la reserva.
- `POST /api/tareas-movimiento/{id}/renovar` prolonga explícitamente su vigencia.
- Liberar o expirar devuelve la tarea a la bandeja y deja libres sus bloqueos, conservando el registro histórico.
- El scheduler ejecuta `tareas:expirar-reservas` cada minuto; las consultas y movimientos realizan además una limpieza perezosa para no depender únicamente del cron.
- Un movimiento manual no puede intervenir un pallet reservado ni ocupar una posición reservada.
- Las recomendaciones omiten posiciones reservadas y el plano informa capacidad física ocupada, reservada y todavía disponible.
- Para ejecutar la tarea, `movimientos/ubicar` o `movimientos/mover` debe incluir `tarea_movimiento_id`; el servicio valida folio, tipo, origen, destino, usuario y dispositivo dentro de la misma transacción.
- El movimiento físico completa atómicamente la tarea y su reserva. Si era la última tarea pendiente, completa también el plan.

La ubicación actual continúa siendo la verdad física. La reserva solo coordina el breve intervalo entre la toma de la tarea y la confirmación del movimiento; un lease abandonado se recupera automáticamente al vencer.

## Estados

Los planes pasan por `programado`, `en_ejecucion`, `pausado`, `completado` o `cancelado`. Las tareas usan `pendiente`, `asumida`, `en_proceso`, `completada` o `cancelada`.

Al asumir la primera tarea, el plan pasa de `programado` a `en_ejecucion` y registra usuario y hora. Al confirmar el movimiento asociado, la tarea pasa a `completada`; el plan se completa cuando ya no conserva tareas pendientes.

## Activación gradual

`WMS_PLANIFICADOR_AUTOMATICO=false` es el valor predeterminado. Los próximos generadores deberán consultar `config('planificador.generacion_automatica')` antes de crear planes desde eventos de Prefrío, Cargas o SAG.

## API preparada

- `GET /api/planes-operacionales`
- `GET /api/planes-operacionales/{id}`
- `GET /api/tareas-movimiento`
- `POST /api/tareas-movimiento/{id}/asumir`
- `POST /api/tareas-movimiento/{id}/renovar`
- `POST /api/tareas-movimiento/{id}/liberar`

Las rutas exigen un usuario autorizado para operar cámaras de producto terminado. Asumir y liberar requieren además un token vinculado a una tablet activa.
