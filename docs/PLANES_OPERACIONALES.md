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

La consulta previa del folio entrega esta recomendación a web y tablet/PDA. Es deliberadamente consultiva: no reserva el destino, no genera una tarea y no ejecuta un movimiento. Esas garantías se incorporarán en los siguientes incrementos.

## Estados

Los planes pasan por `programado`, `en_ejecucion`, `pausado`, `completado` o `cancelado`. Las tareas usan `pendiente`, `asumida`, `en_proceso`, `completada` o `cancelada`.

Al asumir la primera tarea, el plan pasa de `programado` a `en_ejecucion` y registra usuario y hora. Este PR no completa tareas automáticamente: esa transición se conectará al movimiento físico cuando la bandeja operacional llegue a tablet.

## Activación gradual

`WMS_PLANIFICADOR_AUTOMATICO=false` es el valor predeterminado. Los próximos generadores deberán consultar `config('planificador.generacion_automatica')` antes de crear planes desde eventos de Prefrío, Cargas o SAG.

## API preparada

- `GET /api/planes-operacionales`
- `GET /api/planes-operacionales/{id}`
- `GET /api/tareas-movimiento`
- `POST /api/tareas-movimiento/{id}/asumir`
- `POST /api/tareas-movimiento/{id}/liberar`

Las rutas exigen un usuario autorizado para operar cámaras de producto terminado. Asumir y liberar requieren además un token vinculado a una tablet activa.
