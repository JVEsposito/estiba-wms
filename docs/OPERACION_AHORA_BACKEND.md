# Contrato backend de Operación ahora

`GET /api/operacion-ahora` inicia el contrato incremental y no cacheado con el que
la pantalla de Oficina representará la operación real. Esta primera entrega cubre
el estado actual de las cámaras y los controles ambientales. Requiere la capacidad
existente `puede_consultar_panel_gerencial`; no agrega acciones ni modifica
permisos de escritura.

La respuesta incluye:

- fecha y hora en `APP_OPERATIONAL_TIMEZONE` y la temporada activa;
- evidencia de sincronización: última operación recibida y conteos por estado del
  día operacional;
- cámaras activas, ocupación de posiciones operativas y último control ambiental
  vigente, vencido o pendiente.

La ocupación se clasifica como `normal` bajo 70 %, `advertencia` entre 70 % y
90 % inclusive y `critica` sobre 90 %. Es una señal de capacidad, no una
emergencia.

## Decisiones explícitas del contrato

El sistema todavía no posee una configuración transversal de horarios de turno.
Por eso `jornada.turno` se entrega como `null`: la API no deduce A/B a partir de
la hora. `sincronizacion.estado` corresponde al estado de la última operación
registrada y siempre se acompaña de sus timestamps; si no hay evidencia devuelve
`sin_actividad`.

Los controles ambientales solo aplican a cámaras de producto terminado. Para los
otros contenidos `control_ambiental` es `null`. Un registro futuro no se considera
el último control actual.

La ruta responde con `Cache-Control: no-store, private`. El cliente puede usar
`actualizacion_sugerida_segundos` para refrescar, sin asumir que el dato permanece
vigente durante ese intervalo.

Este entregable es exclusivamente backend: no cambia Blade, CSS, JavaScript ni la
aplicación móvil. El panel gerencial histórico continúa en
`GET /api/gerencia/resumen`.

Las próximas entregas aditivas del mismo contrato incorporarán camareros y tareas
activas, prefrío e incidencias. Mantenerlas separadas permite revisar cada fuente
operacional sin convertir la adopción de la pantalla en un PR monolítico.
