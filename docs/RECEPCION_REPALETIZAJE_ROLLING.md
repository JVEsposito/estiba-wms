# Recepción rolling desde Repaletizaje

## Objetivo

Al confirmar un repaletizaje, el WMS convierte cada resultado que sea un pallet completo y tenga prefrío aprobado en una labor operacional para retirarlo del área REPA y ubicarlo. La generación no depende de que el pallet pertenezca a una carga.

El objetivo usa el tipo `recepcion_repaletizaje`, referencia idempotente al UUID del repaletizaje y horizonte `rolling`. Reintentar la misma confirmación no duplica el plan ni sus tareas.

## Elegibilidad

Se planifica únicamente un resultado que cumpla simultáneamente:

- repaletizaje confirmado;
- resultado tipo `pallet`;
- folio activo y tipo pallet;
- condición térmica `prefrio_aprobado`;
- temporada activa.

Los resultados tipo saldo permanecen fuera del planificador de pallets completos. Un pallet `pendiente_prefrio` sigue el circuito térmico vigente y no recibe una instrucción de almacenamiento anticipada.

## Origen y destino

La tarea conserva la ubicación física del resultado cuando REPA heredó una ubicación de origen. En ese caso se crea un `traslado_entre_camaras` con cámara y posición de origen, pero sin destino preasignado.

Si el resultado no posee ubicación física, se crea una `ubicacion_inicial` con origen lógico `repaletizaje`. En ambos casos el contexto declara que el pallet siempre puede retirarse desde REPA y deja el destino para la frontera operacional vigente.

## Prioridad y término

El servidor cuenta los pallets completos cuya labor REPA continúa `pendiente` o
`asumida`. Una labor deja de ocupar el buffer cuando inicia el movimiento físico,
aunque todavía no haya llegado a su destino. La prioridad compartida se recalcula
al generar, tomar, iniciar, liberar, completar o cancelar una labor:

- menos de 8 pallets: `normal`;
- desde 8 pallets: `alta`;
- desde el máximo práctico de 10 pallets: `urgente`.

Los umbrales se configuran con `WMS_REPA_BUFFER_HIGH_FROM_PALLETS` y
`WMS_REPA_BUFFER_MAX_PALLETS`. `critica` permanece reservada para riesgos,
retenciones y camiones confirmados en andén. El plan conserva en su contexto el
conteo y los umbrales usados para que la decisión sea auditable. La selección de
destinos preferentes continúa fuera de este incremento.

El objetivo se completa cuando todas sus tareas, incluidas las cadenas de reemplazo, terminan completadas. Si REPA se anula antes de iniciar su ejecución, el objetivo y sus labores pendientes se cancelan en la misma transacción. Una recepción ya ejecutada bloquea la anulación.

## Activación y rollback

La generación requiere simultáneamente:

- `WMS_PLANIFICADOR_AUTOMATICO=true`;
- `WMS_PLANNER_MODE=guided`;
- `WMS_PLANNER_COMPUTE=tablet`.

Apagar cualquiera de estas condiciones evita nuevas generaciones sin alterar objetivos ya creados.
