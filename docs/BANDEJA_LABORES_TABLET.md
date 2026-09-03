# Bandeja de labores en tablet

Este incremento convierte los planes y tareas operacionales de Frigorífico en un flujo guiado para camareros, sin activar todavía generación automática de planes.

## Flujo

1. Consultar **Mis tareas** o **Disponibles**.
2. Tomar una tarea disponible.
3. Verificar folio por escaneo.
4. Verificar la posición reservada por escaneo.
5. Confirmar el movimiento físico.
6. El backend completa movimiento, tarea, reserva y plan de manera transaccional.

La pantalla conserva acceso explícito a **Plano y operación**, que continúa usando la interfaz anterior sin cambios.

## Concurrencia

La tablet no implementa exclusividad local. Utiliza los endpoints de toma, renovación y liberación incorporados en el PR de reservas operacionales.

Una tarea activa renueva su lease cada cuatro minutos mientras permanece abierta en ejecución. La cuenta regresiva visible deriva de `vence_at`; el backend sigue siendo la fuente de verdad.

Si la reserva vence o el servidor informa conflicto, la confirmación se detiene y la bandeja se vuelve a consultar.

## Validación física

El folio escaneado debe coincidir con `folio.numero_folio`.

Para el destino se aceptan exclusivamente identificadores equivalentes a la posición reservada:

- ID de posición;
- etiqueta de posición;
- etiqueta física `Bxx-Pxx-Nx`.

Una lectura distinta no modifica la ubicación ni libera el destino original.

## Ejecución

La bandeja reutiliza los endpoints existentes de movimiento:

- `POST /api/movimientos/mover` cuando la tarea tiene origen físico;
- `POST /api/movimientos/ubicar` cuando el folio aún no posee posición física.

Ambos reciben `tarea_movimiento_id`, por lo que la finalización operacional permanece bajo control del backend.

Mientras siga vigente el bloqueo completo de cámara, la bandeja abre las sesiones necesarias antes de confirmar. Las sesiones abiertas exclusivamente por la tarea se cierran al terminar o fallar la ejecución.

## Alcance

Este PR no genera tareas automáticamente y no incorpora todavía:

- finalización de túneles;
- presencia de camión en andén;
- concentración automática;
- inspecciones SAG;
- retenciones automáticas;
- movimientos de oportunidad;
- evacuación de cámaras.

Los saldos y REPA continúan fuera del planificador.
