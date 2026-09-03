# Bandeja de labores en tablet

Este incremento convierte los planes y tareas operacionales de Frigorífico en un flujo guiado para camareros y deja preparado el contrato para planificación de horizonte móvil.

La formulación adoptada es:

> **Plan largo, simulación global, reservas cortas, ejecución reactiva.**

El servidor conserva el objetivo y el estado autoritativo. La tablet puede calcular candidatos sobre un snapshot versionado, pero ninguna propuesta modifica la operación hasta que el servidor la valida y materializa.

## Tres dimensiones independientes

```env
WMS_PLANNER_MODE=off
WMS_PLANNER_COMPUTE=server
WMS_PLANNER_HORIZON=batch
WMS_PLANNER_FRONTIER_MAX=4
```

Valores soportados:

- `WMS_PLANNER_MODE`: `off`, `shadow`, `guided`;
- `WMS_PLANNER_COMPUTE`: `server`, `tablet`;
- `WMS_PLANNER_HORIZON`: `batch`, `rolling`.

Los valores por defecto conservan compatibilidad con la operación existente. La combinación objetivo para el piloto futuro será `guided + tablet + rolling`.

## Flujo rolling

1. Consultar **Mis tareas** o **Disponibles**.
2. Tomar una tarea disponible.
3. El servidor crea un **claim exclusivo** de tarea/folio, sin comprometer todavía una posición.
4. Escanear y verificar el folio.
5. Descargar snapshot versionado del plan.
6. La tablet calcula una frontera corta de tareas no conflictivas.
7. La tablet envía las propuestas con versión de tarea, plan y cámara.
8. El servidor acepta o rechaza cada propuesta y materializa solamente destinos todavía válidos.
9. El camarero pulsa **RETIRAR PALLET · INICIAR MOVIMIENTO**.
10. La tarea pasa a `en_proceso`: este es el punto de no retorno.
11. Escanear la posición de destino fija.
12. Confirmar el movimiento físico.
13. El servidor registra el nuevo estado real y el siguiente ciclo recalcula sobre un snapshot nuevo.

La pantalla conserva acceso explícito a **Plano y operación**, que continúa usando la interfaz anterior como respaldo.

## Niveles de compromiso

### Simulación

No bloquea nada. Puede descartarse cuando cambia el snapshot.

### Claim

Bloquea exclusivamente la tarea/folio para un camarero y dispositivo durante el lease. Tomar una tarea no implica reservar inmediatamente una posición en modo `rolling`.

### Reserva física

El servidor añade `posicion_destino_id` y `bloqueo_posicion_id` al mismo lease cuando acepta una propuesta de frontera.

### En proceso

`en_proceso` significa que el pallet ya fue retirado físicamente. La tarea no puede liberarse ni cambiar de destino. La reserva deja de expirar automáticamente hasta que el movimiento se complete o, en una ampliación posterior, se resuelva mediante incidencia.

## Frontera corta

La tablet puede mantener varias tareas tomadas como cola, pero el servidor impide que el mismo camarero/dispositivo tenga más de un pallet `en_proceso` simultáneamente.

`WMS_PLANNER_FRONTIER_MAX` limita cuántas propuestas próximas puede materializar un plan en un ciclo. El valor no equivale necesariamente al número de camareros: el planificador solamente propone tareas para las que encuentra destinos compatibles y no repetidos.

La API de frontera permite aceptación parcial. Si tres propuestas siguen siendo válidas y una quedó obsoleta, las tres válidas se reservan y la restante vuelve a cálculo.

## Snapshot y arbitraje

`GET /api/planes-operacionales/{plan}/snapshot` expone:

- `snapshot_version`;
- versión y estado del plan;
- versiones de tareas;
- versiones de cámaras involucradas;
- configuración `mode / compute / horizon / frontier_max`.

`POST /api/planes-operacionales/{plan}/frontera` recibe propuestas calculadas por la tablet. El servidor vuelve a comprobar:

- snapshot;
- versión de tarea;
- versión del plan;
- versión de cámara;
- propiedad del claim;
- posición activa y libre;
- ausencia de otra reserva física;
- compatibilidad del destino con el tipo de movimiento.

La tablet nunca puede forzar una posición ocupada o ya reservada.

## Punto de no retorno

`POST /api/tareas-movimiento/{tarea}/iniciar` marca explícitamente el comienzo físico.

Antes de `en_proceso`:

- una tarea puede liberarse;
- una propuesta puede quedar obsoleta;
- un destino reservado puede liberarse y recalcularse.

Después de `en_proceso`:

- el destino queda fijo;
- el lease no expira automáticamente;
- la tarea no puede volver a la bandeja;
- el movimiento debe completarse o entrar en incidencia.

## Cierre del plan

En `batch` se conserva temporalmente el cierre histórico cuando no quedan tareas activas.

En `rolling`, terminar la frontera actual **no completa el plan**. El plan representa un objetivo operacional de mayor duración y deberá cerrarse cuando se cumpla la condición de término propia de su tipo. Los evaluadores específicos se incorporarán junto con los generadores de túnel, carga, SAG, retención y evacuación.

## Planificador TypeScript inicial

El PR incorpora un planificador local determinista para establecer el contrato de cálculo en tablet. En este incremento solamente utiliza información ya disponible:

- posición libre y activa;
- reserva existente;
- banda habilitada para tránsito PT;
- cámara compatible con el tipo de movimiento;
- afinidad de cliente/marca/formato cuando el contexto de la tarea la contiene;
- llenado desde el fondo;
- desempate determinista.

No intenta adelantar la simulación avanzada de concentración, SAG, retenciones, oportunidades o evacuación de los PR posteriores.

## Compatibilidad

- los movimientos manuales existentes continúan disponibles fuera de tareas operacionales;
- `batch` conserva la semántica previa de reservas para planes estáticos;
- la bandera histórica `WMS_PLANIFICADOR_AUTOMATICO` se mantiene temporalmente mientras los generadores migran a `WMS_PLANNER_MODE`;
- saldos y REPA continúan fuera del planificador;
- el bloqueo completo actual de cámara se conserva durante el piloto.

## Alcance del PR #250

Este PR define **cómo una tarea abstracta llega a convertirse de manera segura en trabajo físico**. No genera todavía objetivos de negocio automáticamente.

Quedan para los siguientes incrementos:

- #251: objetivo de recepción al finalizar un túnel;
- #252: llegada de camión y replanificación hacia andén;
- #253+: concentración, SAG, retenciones, movimientos de oportunidad y evacuación.
