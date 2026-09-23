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
con fecha en el log de Laravel, tipo, límite, total reactivado, usuario del
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
