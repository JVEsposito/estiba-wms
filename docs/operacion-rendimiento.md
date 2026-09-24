# Perfil operativo y rendimiento

La instalación que atiende tablets, PDA y oficinas por la red de planta debe ejecutarse con un perfil operativo, aunque el servidor esté dentro de la red local. El modo `local` queda reservado para desarrollo y diagnóstico.

## Variables recomendadas

```dotenv
APP_ENV=production
APP_DEBUG=false
TELESCOPE_ENABLED=false

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900

LOG_LEVEL=warning
SANCTUM_TRACK_LAST_USED_AT=false
```

Después de modificar `.env`, reconstruir las cachés de Laravel:

```bash
php artisan optimize:clear
php artisan optimize
```

Este perfil reduce escrituras y lecturas auxiliares en MySQL:

- Telescope no registra cada petición, consulta, modelo y excepción.
- Caché y sesiones no compiten con las transacciones operacionales en la base de datos.
- Sanctum no actualiza `personal_access_tokens.last_used_at` en cada petición autenticada. La autenticación, expiración y validación de usuario y dispositivo permanecen activas.
- La cola continúa en MySQL mientras exista un solo servidor. Además de las
  auditorías manuales, procesa el arbitraje desacoplado del planificador; por eso
  el worker es obligatorio en `shadow` y `guided`. Si la carga crece o se agregan
  servidores, Redis es el siguiente paso recomendado. La conexión `sync` no
  ejecuta el árbitro dentro de una mutación: deja la proyección pendiente para
  impedir que una mala configuración vuelva a acoplar el cálculo a la solicitud.

## Cola de trabajo

Las auditorías manuales de Salud operacional se envían a la cola para no mantener abierta la petición del navegador mientras se recorren los folios y procesos. La instalación debe mantener un worker supervisado con un tiempo límite menor que `DB_QUEUE_RETRY_AFTER`:

```bash
php artisan queue:work --sleep=1 --tries=5 --timeout=840
```

Después de cada despliegue se debe reiniciar el worker para que cargue el código nuevo:

```bash
php artisan queue:restart
```

El scheduler conserva la auditoría automática cada 15 minutos y, cada minuto,
verifica que la proyección de arbitraje tenga un job vigente. El bloqueo
compartido evita que una ejecución manual y una programada de integridad
escriban resultados al mismo tiempo.

Los recálculos de concentración, segregación, reordenamiento, desocupación y
prioridad del buffer REPA dejan una solicitud persistida en la misma transacción
que origina el cambio. El worker los procesa fuera de la petición HTTP. El
scheduler ejecuta `planificador:recuperar-proyecciones` cada minuto para volver
a publicar solicitudes pendientes. Una proyección confirmada elimina su fila;
si llega una versión nueva durante el cálculo, la fila permanece pendiente.
`/api/administracion/planificador/salud` informa `proyecciones_pendientes`:
total pendiente, atrasados más de cinco minutos, fallidos recuperables,
agotados, descartados por fuente inexistente y edad del más antiguo. Un fallo
funcional se intenta como máximo cinco veces; después queda agotado y el
watchdog deja de republicarlo. Los descartes y agotamientos se conservan como
señal diagnóstica sin contaminar el contador de trabajo pendiente.

Después de corregir y desplegar la causa de un agotamiento, un operador puede
reactivar hasta 200 solicitudes por ejecución y volver a enviarlas al worker:

```bash
php artisan planificador:recuperar-proyecciones --reintentar-agotados --limite=200
```

Si se corrigió la causa de un solo tipo de proyección, limitar la intervención
para no reactivar fallos de otros tipos, por ejemplo:

```bash
php artisan planificador:recuperar-proyecciones --reintentar-agotados --tipo=concentracion_carga --limite=200
```

Los tipos disponibles son `concentracion_carga`, `concentracion_movimiento`,
`segregacion_movimiento`, `reordenamiento_movimiento`,
`desocupacion_movimiento` y `prioridad_buffer_repa`. Cada reactivación se registra
con nivel `warning` y fecha en el log de Laravel, tipo, límite, total reactivado, usuario del
sistema operativo si está disponible y servidor. Ese usuario identifica el
proceso de consola; para identificar a una persona deben usarse las bitácoras
de acceso del servidor.

El scheduler ordinario nunca reactiva agotados. El comando requiere una cola
distinta de `sync`, respeta el límite y conserva los descartes por fuentes
eliminadas. Confirmar luego en Salud que `agotados` baje y que `pendientes`
vuelva a cero; revisar también `failed_jobs` si la causa fue una excepción.

Antes de habilitar el modo guiado en planta, comprobar en la instalación real
que cron ejecuta `schedule:run`, el worker supervisado procesa la cola de base
de datos, `failed_jobs` se consulta y las proyecciones pendientes regresan a
cero después de un movimiento de prueba. Revisar además los respaldos reales
de MySQL y realizar una restauración de prueba: la documentación del repositorio
no demuestra por sí sola que estas tareas estén configuradas fuera de él.

## Telescope

Telescope es una herramienta de diagnóstico local y no contiene datos operacionales. En desarrollo puede habilitarse temporalmente con `APP_ENV=local` y `TELESCOPE_ENABLED=true`. La tarea programada conserva 48 horas de historial siempre que el scheduler de Laravel esté funcionando.

Antes de eliminar un historial acumulado se debe generar un respaldo reciente de la base operacional. La limpieza debe limitarse a estas tablas:

- `telescope_entries_tags`
- `telescope_entries`
- `telescope_monitoring`

No se deben incluir tablas de folios, procesos, movimientos, validaciones ni usuarios en esa limpieza.

## Servidor HTTP

`php artisan serve` es adecuado para una prueba puntual. La instalación de planta debe publicarse mediante Apache o Nginx con el document root apuntando a `public/`, OPcache habilitado y HTTPS cuando la red lo permita.

## Volumen de referencia y banco de pruebas

La planta procesa del orden de **150.000 folios PT por temporada** (nunca simultáneos: la
capacidad de cámaras y túneles ronda los 1.000 pallets) con 9 dispositivos en turno. La carga
concurrente es baja; lo que crece es el historial: validaciones, movimientos, trazabilidad y
operaciones sincronizadas, que se acumulan temporada tras temporada.

`scripts/rendimiento/generar-volumen.sql` crea en una base **de pruebas** 150.000 folios en la
temporada activa y 150.000 en otra, 600.000 líneas de trazabilidad y 500.000 operaciones
sincronizadas. El script se detiene si el nombre de la base no contiene `bench`, `prueba` o
`test`.

```bash
mysql -u root -p -e "CREATE DATABASE estiba_bench"
DB_DATABASE=estiba_bench php artisan migrate --seed
mysql -u root -p estiba_bench < scripts/rendimiento/generar-volumen.sql
DB_DATABASE=estiba_bench php artisan tinker --execute="$(sed 1d scripts/rendimiento/medir.php)"
```

Resultados con ese volumen (MariaDB local, mediana de 3 ejecuciones):

| Consulta | Antes | Después |
|---|---|---|
| Búsqueda global de folios | 2,2 – 2,6 s | 0,21 – 0,28 s |
| Operación ahora: última sincronización | 1,4 s | 1 ms |
| Operación ahora: sincronizaciones del día | 3,2 s | 25 ms |
| Operación ahora completa (24 consultas) | > 4,6 s | 38 ms |
| Trazabilidad de un lote o proceso (página) | 17 – 21 s | 29 – 53 ms |
| Recorrer 40.000 despachos para Excel | 271 s (OFFSET) | 2,1 s (por clave) |

Criterios que sostienen estos tiempos:

- **Índices** en `operaciones_sincronizacion.recibida_servidor_at` y en
  `folios (temporada_id, numero_folio, fecha_ingreso)`.
- **Nada de `id IN (subconsulta) OR …`** sobre folios: se resuelven primero los IDs con sus
  índices y luego se consultan esos folios.
- **Búsqueda global de folios**: número exacto en cualquier temporada, fragmento del número
  (por ejemplo los últimos dígitos) en la temporada activa, y lote MP o proceso de packing
  desde la trazabilidad. Ya no busca texto libre (variedad, marca, exportadora) ni dentro del
  JSON de datos externos; para eso están los filtros de cada oficina.
- **Exportaciones grandes** recorren por clave (`lazyById` o fecha + id) y no con `lazy()`, que
  pagina con OFFSET y relee todas las filas anteriores en cada bloque. Los filtros (cliente,
  período) se aplican en la base, no en PHP.
