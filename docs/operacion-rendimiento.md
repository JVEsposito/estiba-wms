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
- La cola continúa en MySQL mientras exista un solo servidor. Si la carga crece o se agregan servidores, Redis es el siguiente paso recomendado.

## Telescope

Telescope es una herramienta de diagnóstico local y no contiene datos operacionales. En desarrollo puede habilitarse temporalmente con `APP_ENV=local` y `TELESCOPE_ENABLED=true`. La tarea programada conserva 48 horas de historial siempre que el scheduler de Laravel esté funcionando.

Antes de eliminar un historial acumulado se debe generar un respaldo reciente de la base operacional. La limpieza debe limitarse a estas tablas:

- `telescope_entries_tags`
- `telescope_entries`
- `telescope_monitoring`

No se deben incluir tablas de folios, procesos, movimientos, validaciones ni usuarios en esa limpieza.

## Servidor HTTP

`php artisan serve` es adecuado para una prueba puntual. La instalación de planta debe publicarse mediante Apache o Nginx con el document root apuntando a `public/`, OPcache habilitado y HTTPS cuando la red lo permita.
