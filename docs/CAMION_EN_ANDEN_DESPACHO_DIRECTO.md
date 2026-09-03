# Camión en andén y despacho directo

## Objetivo

Cuando oficina confirma que el camión de una carga ya se encuentra físicamente en un andén, el WMS cambia el objetivo prioritario de esos pallets: siempre que la realidad física lo permita, deben salir al andén sin ocupar una posición intermedia de cámara.

La presencia genera un objetivo rolling `despacho_directo` de prioridad crítica. El servidor mantiene el estado autoritativo y publica el conjunto de acciones físicamente ejecutables; la tablet conserva el cálculo/materialización de destinos para los movimientos de despeje.

La frontera puede contener varias labores independientes en paralelo:

- retiros directos de pallets de la carga que ya están accesibles;
- retiros directos desde un Prefrío aprobado, sin paso por cámara;
- el primer bloqueador exterior que debe moverse para habilitar un pallet de la carga.

Dos labores que requieren el mismo pallet no se publican simultáneamente. Un pallet propio accesible se retira al andén en vez de tratarlo como bloqueador a reubicar.

## Registro de presencia

La pantalla de cargas permite registrar:

- carga;
- andén;
- patente;
- conductor opcional;
- fecha y hora física de llegada;
- observación.

La presencia activa es exclusiva por carga y por andén. Los campos de bloqueo se limpian al finalizar, conservando el historial completo. Tanto el ingreso como la salida utilizan UUID de operación y hash de payload para soportar reintentos idempotentes.

El registro de presencia pertenece al estado físico de la operación y existe incluso con el planificador apagado.

## Frontera rolling y varios camareros

El plan ya no mantiene una única “próxima tarea”. Puede publicar varias acciones independientes para que distintos camareros reclamen trabajo simultáneamente.

La cantidad efectivamente ejecutada queda limitada por:

```text
min(
  camareros disponibles,
  labores físicamente independientes,
  WMS_PLANNER_FRONTIER_MAX
)
```

Los retiros directos tienen un destino lógico ya determinado: el andén confirmado por oficina. Los despejes, en cambio, nacen sin posición de destino; la tablet usa el snapshot rolling de #250 y el servidor arbitra/reserva la posición física propuesta.

El servidor no inventa una ubicación final para el bloqueador ni compromete toda la secuencia futura de la carga.

## Prefrío → andén directo

Un pallet aprobado en Prefrío puede pertenecer a una recepción `recepcion_tunel` creada por #251 y, posteriormente, quedar priorizado por la llegada de su camión.

En ese caso:

1. la tarea de recepción todavía reversible se cancela explícitamente;
2. queda enlazada a su tarea de reemplazo `despacho_directo`;
3. el nuevo retiro conserva como origen lógico el túnel y la posición de Prefrío;
4. el camarero escanea el folio y pulsa **RETIRAR PALLET**;
5. la tarea entra en `en_proceso`, igual que cualquier movimiento físico;
6. al confirmar la entrega se actualiza la carga y el folio pasa a `en_anden`.

No se crea una `UbicacionActual`, una sesión de cámara ni un `Movimiento` de cámara ficticio. La trazabilidad queda en la tarea, la presencia, la asignación de carga y los eventos de despacho.

La recepción original de #251 solo se considera resuelta cuando la cadena de reemplazo termina realmente en una tarea completada. Cancelar la ubicación por sí sola no declara vacío el túnel.

## Prioridad y replanificación

Si una tarea afectada se encuentra:

- `pendiente` o `asumida`: puede cancelarse/reemplazarse de forma auditable;
- `en_proceso`: no cambia de destino ni se reasigna, porque ya cruzó el punto de no retorno.

La cancelación conserva usuario, fecha, motivo y vínculo con la tarea de reemplazo. Una presencia tampoco puede finalizar mientras alguna de sus tareas se encuentre `en_proceso`.

Después de un movimiento no se recalculan todos los camiones de la planta. Se identifican las cargas afectadas por:

- el folio movido;
- la banda/nivel de origen;
- la banda/nivel de destino.

Solo las presencias relacionadas vuelven a sincronizar su frontera.

## Ejecución en tablet/PDA

Para retiro desde cámara:

1. tomar tarea;
2. escanear folio;
3. **RETIRAR PALLET**;
4. confirmar entrega en el andén.

El retiro físico crea el movimiento normal de salida desde cámara y no crea una ubicación ficticia de andén.

Para retiro desde Prefrío, el mismo flujo visual se conserva, pero no se abre una sesión de cámara. La confirmación utiliza el endpoint especializado de despacho directo desde Prefrío.

Los despejes siguen el contrato de #250:

1. tomar tarea;
2. escanear folio;
3. calcular/materializar destino con snapshot vigente;
4. **RETIRAR PALLET**;
5. completar el movimiento reservado.

## Modos de despliegue

### `off`

La presencia se registra y audita, pero no se crean planes ni tareas.

### `shadow`

Se evalúa el conjunto de acciones físicamente candidatas y se registra como evento de planificación, sin dirigir trabajo ni materializar tareas.

### `guided`

Se publica el objetivo rolling y la bandeja conduce la ejecución.

Configuración objetivo de piloto:

```env
WMS_PLANNER_MODE=guided
WMS_PLANNER_COMPUTE=tablet
WMS_PLANNER_HORIZON=rolling
WMS_PLANIFICADOR_AUTOMATICO=true
```

El horizonte del plan `despacho_directo` queda fijado en `rolling` dentro del propio contexto del objetivo.

## Finalización

Oficina puede liberar el andén indicando un motivo. Las tareas todavía reversibles se cancelan y se liberan sus claims o destinos. Si alguna tarea está `en_proceso`, la liberación se rechaza.

Cuando ya no quedan pallets completos pendientes de la carga fuera del andén, el objetivo `despacho_directo` se completa. El cierre documental de la carga puede finalizar automáticamente la presencia del camión.

## Fuera de alcance

Este incremento no convierte el despacho directo en el planificador de concentración de cargas. La concentración/separación general, sus scores y movimientos oportunistas corresponden al siguiente incremento del programa.
