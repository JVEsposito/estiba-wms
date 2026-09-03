# Camión en andén y despacho directo

## Objetivo

Cuando oficina confirma que el camión de una carga ya se encuentra físicamente en un andén, el WMS evita concentrar esos pallets en otra cámara. En su lugar, crea una frontera rolling crítica que propone un solo movimiento inmediato:

- retirar directamente al andén un pallet completo asignado y accesible; o
- despejar el pallet completo exterior que impide acceder a la carga.

Después de cada movimiento el servidor vuelve a calcular la decisión. Saldos y REPA permanecen fuera de este flujo.

## Registro de presencia

La pantalla de cargas permite registrar:

- carga;
- andén;
- patente;
- conductor opcional;
- fecha y hora física de llegada;
- observación.

La presencia activa es exclusiva por carga y por andén. Los campos de bloqueo se limpian al finalizar, conservando el historial completo. Tanto el ingreso como la salida utilizan UUID de operación y hash de payload para soportar reintentos idempotentes.

## Prioridad y replanificación

La presencia origina un plan `despacho_directo` de prioridad `critica`, referenciado por la presencia. Solo existe una tarea activa de este objetivo a la vez.

Si el pallet elegido ya posee una tarea:

- `pendiente` o `asumida`: puede cancelarse explícitamente y queda enlazada con la tarea que la reemplazó;
- `en_proceso`: no se modifica, porque el pallet ya cruzó el punto de no retorno.

La cancelación conserva usuario, fecha y motivo. Una presencia tampoco puede finalizar mientras alguna de sus tareas se encuentre `en_proceso`.

## Ejecución en tablet/PDA

La tarea de retiro muestra el andén como destino lógico. El camarero:

1. toma la tarea;
2. escanea el folio;
3. inicia el movimiento físico;
4. confirma la entrega en el andén indicado.

La confirmación utiliza el flujo existente `enviar-anden`, asociando el movimiento, la tarea, la carga, la presencia y el andén. No se crea una ubicación ficticia para el andén.

## Finalización

Oficina puede liberar el andén indicando un motivo. Las tareas todavía reversibles se cancelan y se liberan sus claims o destinos. Si la carga completa su salida documental, la presencia se finaliza automáticamente con el mismo evento operacional.

## Activación y rollback

La presencia puede registrarse aun con el planificador apagado, pero no modifica ni genera tareas. Para activar la conducción automática se requiere:

```env
WMS_PLANNER_MODE=guided
WMS_PLANIFICADOR_AUTOMATICO=true
```

Volver `WMS_PLANNER_MODE` a `off` detiene la generación y el recálculo de nuevas tareas sin eliminar presencias, planes, movimientos ni auditoría histórica.
