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

Este incremento usa prioridad base `normal`. La ocupación máxima del buffer REPA, el escalamiento al acercarse a diez pallets y la selección de destinos preferentes se incorporan en incrementos posteriores.

El objetivo se completa cuando todas sus tareas, incluidas las cadenas de reemplazo, terminan completadas. Si REPA se anula antes de iniciar su ejecución, el objetivo y sus labores pendientes se cancelan en la misma transacción. Una recepción ya ejecutada bloquea la anulación.

## Activación y rollback

La generación requiere simultáneamente:

- `WMS_PLANIFICADOR_AUTOMATICO=true`;
- `WMS_PLANNER_MODE=guided`;
- `WMS_PLANNER_COMPUTE=tablet`.

Apagar cualquiera de estas condiciones evita nuevas generaciones sin alterar objetivos ya creados.
