# Salud y despliegue del planificador

## Despliegue progresivo

El modo global sigue siendo el techo de seguridad:

```dotenv
WMS_PLANNER_MODE=guided
WMS_PLANNER_COMPUTE=tablet
WMS_PLANNER_HORIZON=rolling
WMS_PLANIFICADOR_AUTOMATICO=true
WMS_PLANNER_ROLLOUT_CAMERAS=CAM-01
```

Una emergencia solo dirige y bloquea la cámara cuando `guided` coincide con
generación automática, cálculo `tablet` y horizonte `rolling`. Una combinación
incompleta se rechaza antes de crear el plan o modificar labores y bandas.

`WMS_PLANNER_ROLLOUT_CAMERAS` admite UUID o códigos separados por coma. Si la
lista está vacía, se conserva el comportamiento global anterior. Si contiene
cámaras y el modo global es `guided`, solo ellas publican trabajo dirigido; las
demás continúan en `shadow`.

El rollback operacional es siempre:

```dotenv
WMS_PLANNER_MODE=off
```

`off` prevalece sobre cualquier lista y evita nuevas decisiones dirigidas. Las
maniobras que ya cruzaron `en_proceso` conservan la realidad física como fuente
de verdad y deben cerrarse de acuerdo con sus interbloqueos.

## Ciclos de cámara

Las emergencias y desocupaciones de una misma cámara se registran en ciclos
independientes. Repetir el aviso de un ciclo activo devuelve ese plan; después
de completarlo o cancelarlo, un nuevo aviso crea otro plan y conserva intactos
el motivo, responsables, fechas, tareas, maniobras y movimientos del anterior.
La apertura mantiene el bloqueo transaccional de la cámara para serializar
avisos simultáneos. La selección del último ciclo no depende de la hora del aviso.

La migración asigna el ciclo 1 a las referencias existentes. Los demás
generadores conservan ese valor y su unicidad habitual. Si ya existen ciclos
posteriores, revertir esa migración se rechaza antes de modificar el esquema;
el rollback operacional mediante `WMS_PLANNER_MODE=off` sigue disponible.

## Snapshot de salud

`GET /api/administracion/planificador/salud` requiere permiso de consulta de
integridad operacional. Por defecto cubre las últimas ocho horas y acepta:

- `desde` y `hasta`, con una ventana máxima de siete días;
- `camara_id`, para observar una sola cámara durante el piloto.

La respuesta incluye un `snapshot_version` SHA-256, la configuración efectiva
por cámara, métricas de planes, maniobras, tareas, movimientos, reservas y
discrepancias, más señales actuales de riesgo. `critico` identifica leases
vencidos todavía activos o maniobras completadas con custodia temporal;
`advertencia` identifica tareas estancadas, custodias activas o discrepancias
abiertas.

Para ampliar el rollout, mida una o dos jornadas de la cámara piloto, conserve
el snapshot de cada ventana y agregue el siguiente código solo cuando la salud
permanezca estable y la desviación entre plan y ejecución sea aceptable.
