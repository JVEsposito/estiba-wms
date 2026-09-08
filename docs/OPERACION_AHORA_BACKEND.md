# Contrato backend de Operación ahora

`GET /api/operacion-ahora` expone el contrato incremental y no cacheado con el que
la pantalla de Oficina representará la operación real. Cubre el estado actual de
las cámaras, los controles ambientales, los camareros con sesión de estiba abierta
y los túneles de prefrío. Requiere la capacidad existente
`puede_consultar_panel_gerencial`; no agrega acciones ni modifica permisos de
escritura.

La respuesta incluye:

- fecha y hora en `APP_OPERATIONAL_TIMEZONE` y la temporada activa;
- evidencia de sincronización: última operación recibida y conteos por estado del
  día operacional;
- camareros activos, dispositivo, cámara en la que mantienen la sesión abierta y
  su tarea actual de la temporada activa;
- túneles de prefrío, disponibilidad, ocupación física y proceso activo de la
  temporada;
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

`camareros` usa la sesión abierta como fuente de la ubicación actual. No deduce la
ubicación desde el origen o destino de una tarea. `tarea_actual` considera solo
tareas `en_proceso` o `asumida` cuyo plan pertenezca a la temporada activa y cuya
combinación de usuario y dispositivo coincida con la sesión. Si hay más de una,
prioriza `en_proceso`, luego la prioridad operacional y finalmente la más antigua.
Una sesión abierta sin trabajo tomado se conserva con `tarea_actual: null`; las
sesiones cerradas no aparecen.

`prefrio` separa el estado administrativo, técnico y operacional de cada túnel.
La capacidad corresponde a posiciones activas y la ocupación cuenta posiciones
físicas distintas, por lo que varios saldos apilados suman varios folios pero una
sola posición. Un túnel activo y operativo sin proceso aparece como `disponible`;
si posee un proceso activo adopta el estado de ese proceso. Las posiciones vacías
solo se informan como disponibles mientras el proceso aún admite carga; una vez
iniciado, no se ofrecen como capacidad para otro proceso.

El campo `avance_tiempo_objetivo_porcentaje` compara el tiempo desde el inicio con
la duración objetivo y se limita a 100 %. No representa avance térmico ni sustituye
la verificación de temperatura. Al pasar a `pendiente_verificacion`, el tiempo se
corta en ese evento en vez de continuar aumentando mientras espera la decisión
del supervisor. Las mediciones térmicas continuas requerirán telemetría futura.

La ruta responde con `Cache-Control: no-store, private`. El cliente puede usar
`actualizacion_sugerida_segundos` para refrescar, sin asumir que el dato permanece
vigente durante ese intervalo.

Este entregable es exclusivamente backend: no cambia Blade, CSS, JavaScript ni la
aplicación móvil. El panel gerencial histórico continúa en
`GET /api/gerencia/resumen`.

La próxima entrega aditiva del mismo contrato incorporará incidencias. Mantener
las fuentes separadas permite revisar cada una sin convertir la adopción de la
pantalla en un PR monolítico.
