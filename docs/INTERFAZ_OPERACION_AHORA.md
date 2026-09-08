# Interfaz de Operación ahora

La oficina `/oficina/operacion-ahora` representa el contrato de solo lectura
`GET /api/operacion-ahora`. Su objetivo es entregar una vista operativa actual,
densa y verificable para gerencia y supervisión, separada del panel gerencial
histórico.

## Contenido visible

- jornada, temporada, hora del servidor y evidencia de la última actualización;
- resumen de cámaras activas, ocupación PT y controles ambientales requeridos;
- camareros con sesión abierta, dispositivo, cámara actual y tarea vigente;
- túneles de prefrío, capacidad física, proceso activo y avance temporal;
- incidencias abiertas de carga y discrepancias de maniobra;
- alertas verificables derivadas de ocupación PT, vigencia del control ambiental,
  procesos de prefrío fuera de objetivo y conflictos de sincronización;
- conteos diarios de sincronizaciones aceptadas, pendientes, en proceso,
  rechazadas y con conflicto;
- vista esquemática construida únicamente con cámaras y túneles entregados por
  el contrato, sin afirmar que representa coordenadas físicas;
- accesos directos a cámaras, prefrío, cargas e incidencias existentes.

El avance de prefrío se identifica explícitamente como tiempo transcurrido. La
interfaz no lo presenta como progreso térmico ni inventa lecturas que el backend
no entrega.

Las alertas se calculan en el cliente exclusivamente desde el mismo snapshot y
siempre incluyen evidencia y un acceso a la oficina donde puede revisarse la
condición. No representan una nueva severidad persistida ni ejecutan acciones de
negocio.

## Contrato visual

La composición toma como referencia las maquetas corporativas entregadas para
ESTIBA: topbar navy, navegación lateral compacta, encabezados oscuros por bloque,
tablas operacionales, barras de ocupación visibles y una jerarquía continua de
centro de control. Los indicadores redundantes no forman una franja adicional:
se integran en los encabezados de cada panel, junto a la lectura que explican.
La sincronización y sus cinco estados diarios se concentran en el bloque de
estado de datos de la cabecera.

Los paneles corresponden a consultas concretas: dónde están los camareros, qué
cámaras requieren atención, qué túneles están activos, qué dispositivos están
sincronizando y qué excepciones siguen abiertas. No se usan gradientes,
sombras pesadas, radios grandes ni datos de muestra. Si no existe control
ambiental, la columna de temperatura declara `SIN REGISTRO`. En escritorio los
seis bloques de información se distribuyen en una grilla compacta de tres filas,
con altura acotada y desplazamiento interno cuando existen más registros. En
pantallas angostas vuelven a una sola columna y recuperan su altura natural.

`Operación ahora` se expone como acceso principal del shell, por encima de los
módulos administrativos. El selector de apariencia y la actualización de contexto siguen disponibles,
pero quedan dentro de `Preferencias de interfaz` para no competir con la
navegación operacional.

## Actualización y fallos

La primera consulta bloquea el espacio de trabajo. Después, el cliente respeta
`actualizacion_sugerida_segundos`, evita solicitudes superpuestas, pausa cuando
la pestaña está oculta o el equipo está sin conexión y aplica retroceso gradual
después de errores. Si una actualización falla, conserva la última lectura y
muestra su hora en vez de reemplazarla por ceros.

## Acceso y alcance

La navegación y el cliente exigen `puede_consultar_panel_gerencial` y el módulo
`gerencia.panel`. La pantalla no expone formularios ni acciones operativas. Los
enlaces llevan a las oficinas existentes que poseen sus propios permisos.

Este entregable no modifica el endpoint, reglas de negocio, permisos, modelos,
migraciones ni aplicación móvil.
