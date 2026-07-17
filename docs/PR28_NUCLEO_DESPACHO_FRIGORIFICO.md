# PR #28 — Núcleo del despacho frigorífico

Este PR incorpora el dominio transaccional del despacho de productos. No añade todavía la interfaz de oficina, las pantallas de la APK ni las notificaciones persistentes de los PR siguientes.

## Conceptos

- Una carga `CAR-*` reserva folios de producto desde el borrador hasta su cierre o liberación.
- Una tarea de carga representa trabajo pendiente en una cámara. Es compartida: no bloquea la carga a un único camarero.
- El bloqueo físico sigue perteneciendo a la sesión de estiba de la cámara y solo existe mientras se ejecutan movimientos.
- Un andén es un destino documental administrable. Enviar un folio al andén crea un movimiento físico inmutable de tipo `retiro` y libera su posición.
- `despachada` significa que todos los folios vigentes están en andén. `cerrada` significa que la salida del camión fue confirmada en oficina.

## Estados individuales del folio en una carga

| Estado | Significado |
|---|---|
| `pendiente` | Reservado y disponible para preparación. |
| `con_incidencia` | Excluido temporalmente de la ruta hasta su resolución. |
| `en_anden` | Retirado de cámara y entregado al andén. |
| `descartado` | Liberado mediante despacho parcial o desasignación. |
| `reemplazado` | Liberado y sustituido por otro folio compatible. |

No se persiste un estado `en_transito`: el movimiento físico se confirma de forma atómica. Si posteriormente se implementa un flujo de dos pasos, ese estado podrá añadirse junto con recuperación de operaciones incompletas.

## Incidencias

El reporte exige usuario, tablet, sesión abierta y bloqueo de la cámara donde está ubicado el folio. Cada reporte y cada resolución usan un UUID idempotente y un hash del contenido.

Resoluciones disponibles:

- `despacho_parcial`: libera el folio, pero exige que la carga conserve al menos otro folio.
- `reemplazo`: libera el original y reserva otro folio compatible en tipo, condición SAG, variedad, calibre, marca y exportadora.
- `reparado`: devuelve el mismo folio al estado pendiente.

Despacho parcial y reemplazo son resoluciones comerciales para administrador o despachador. La reparación también puede confirmarla el supervisor de frío.

## Integridad y concurrencia

- `reservas_carga_folio` garantiza en MySQL una sola carga vigente por folio sin borrar sus asignaciones históricas.
- Las operaciones sensibles utilizan transacciones y bloqueos pesimistas.
- Los retiros reutilizan `operaciones_sincronizacion`, por lo que repetir el mismo UUID y payload no duplica movimientos.
- Reutilizar un UUID con datos diferentes genera conflicto.
- El cierre marca los folios como despachados e inactivos, libera las reservas y conserva la auditoría completa.

## API incorporada

```text
GET  /api/andenes
POST /api/administracion/andenes
PUT  /api/administracion/andenes/{anden}

GET  /api/cargas/{carga}/tareas
POST /api/cargas/asignaciones/{cargaFolio}/incidencias
POST /api/cargas/incidencias/{incidencia}/resolver
POST /api/cargas/asignaciones/{cargaFolio}/enviar-anden
POST /api/cargas/{carga}/cerrar-despacho
```

## Entregas posteriores

- PR #29: interfaz de oficina, búsqueda paginada, seguimiento, concentración y resolución comercial.
- PR #30: bandeja y operación de cargas en APK, ruta vertical e incidencias en terreno.
- PR #31: notificaciones persistentes y polling tolerante a conectividad intermitente.
