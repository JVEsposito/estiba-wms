# PR #32 — Notificaciones operacionales

Esta entrega completa la comunicación entre oficina y cámaras para las cargas
frigoríficas. Las notificaciones se guardan en MySQL y el cliente móvil conserva
el último estado válido cuando la red deja de responder.

## Persistencia y audiencias

Una notificación posee una clave idempotente, tipo, severidad, mensaje y una
audiencia. La audiencia puede apuntar a:

- un área operacional, como `productos`;
- un rol, como `despachador`;
- un usuario específico.

El sistema no copia una alerta para cada cuenta existente. Esto permite que un
usuario nuevo o el camarero del turno siguiente consulte las alertas todavía
vigentes de su área. La lectura y la confirmación sí se registran individualmente
por usuario.

## Disparadores

Los eventos de carga generan notificaciones después de confirmar la transacción:

| Evento | Audiencia | Severidad |
|---|---|---|
| Carga publicada | Área de productos | Informativa |
| Prioridad modificada en una carga publicada | Área de productos | Advertencia o crítica |
| Incidencia reportada | Administrador, supervisor de frío y despachador | Crítica |
| Pallet reparado o reemplazado | Área de productos | Éxito |

El observador de `EventoCarga` despacha un evento de dominio y su listener usa
una clave derivada del evento y la audiencia. Repetir la entrega no duplica la
notificación.

## API

```text
GET  /api/notificaciones-operacionales
POST /api/notificaciones-operacionales/{notificacion}/leer
POST /api/notificaciones-operacionales/{notificacion}/confirmar
```
La API filtra en Laravel según el usuario autenticado. Conocer un UUID ajeno no
permite leer ni confirmar la alerta de otra área.

## APK y conectividad

- El encabezado muestra el contador de alertas sin leer.
- El centro de notificaciones permite leer, confirmar y abrir el módulo de cargas.
- Las alertas se consultan cada 12 segundos y al volver a la aplicación.
- La bandeja de cargas y su ruta se actualizan silenciosamente cada 12 segundos.
- Solo existe una consulta en vuelo por módulo.
- Una pérdida de red no vacía la bandeja ni elimina las alertas descargadas.
- Al recuperar conectividad, el siguiente ciclo reemplaza el estado local por el confirmado en MySQL.

## Despliegue

Esta entrega agrega migraciones, pero no incorpora dependencias nativas en la
APK. El backend debe desplegarse y migrarse antes de publicar la actualización
OTA de Expo.

```text
1. Poner Laravel en mantenimiento.
2. Actualizar el código.
3. Ejecutar php artisan migrate --force.
4. Ejecutar php artisan optimize:clear.
5. Levantar Laravel.
6. Publicar EAS Update o iniciar Expo Go para pruebas.
```
