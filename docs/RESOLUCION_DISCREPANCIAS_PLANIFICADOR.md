# Resolución de discrepancias del planificador

`NO COINCIDE` sigue siendo una acción del camarero. Pausa la maniobra, registra
el folio, la tarea, el usuario, la tablet, la fecha y el detalle, y conserva las
protecciones que ya cruzaron el punto de no retorno.

La resolución pertenece a supervisión de frío. El backend ofrece:

```text
POST /api/discrepancias-maniobra/{discrepancia}/resolver
```

La bandeja supervisora consulta exclusivamente la temporada activa mediante:

```text
GET /api/discrepancias-maniobra
```

Admite estado, búsqueda y paginación. Cada caso entrega folio, maniobra y su
versión, paso afectado, origen, destino, reportante, dispositivo, auditoría de
resolución y la causa que impide cancelar cuando existe trabajo físico en curso.

## Acciones

### `reanudar_maniobra`

- Si el paso no comenzó, vuelve a la bandeja como pendiente y puede ser tomado
  por un camarero disponible.
- Si existe un paso `en_proceso`, conserva su camarero, tablet y reserva para
  que ese mismo movimiento pueda cerrarse.
- Si existe custodia temporal, conserva la banda protegida y publica el primer
  paso no ejecutado de la secuencia cerrada.
- Si el último paso terminó mientras la maniobra estaba pausada, finaliza la
  maniobra y libera sus bandas solamente cuando no queda custodia activa.

### `cancelar_maniobra`

Cancela únicamente los pasos pendientes, asumidos o bloqueados. Los pasos ya
completados y la ubicación física resultante permanecen intactos. El plan queda
disponible para que su generador específico lo evalúe nuevamente desde el
estado real.

Esta acción se rechaza cuando existe un paso `en_proceso` o una custodia
temporal activa. En esos casos supervisión debe reanudar el cierre físico.

## Concurrencia y auditoría

La resolución requiere:

```json
{
  "accion": "reanudar_maniobra",
  "version_maniobra": 7,
  "resolucion": "Folio y posición verificados en terreno."
}
```

La versión evita resolver una lectura obsoleta. La operación bloquea tarea,
maniobra y discrepancia dentro de una transacción. Repetir la misma acción es
idempotente; intentar otra acción después de resolver produce conflicto.

Se conservan `resuelta_por_user_id`, `resuelta_at`, `accion_resolucion` y el
fundamento escrito. La migración no puede revertirse después de registrar una
acción porque eliminaría parte de la trazabilidad.
