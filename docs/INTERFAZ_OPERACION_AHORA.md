# Interfaz de Operación ahora

La oficina `/oficina/operacion-ahora` representa el contrato de solo lectura
`GET /api/operacion-ahora`. Su objetivo es entregar una vista operativa actual,
densa y verificable para gerencia y supervisión, separada del panel gerencial
histórico.

## Contenido visible

- jornada, temporada, hora del servidor y evidencia de la última actualización;
- puesto de mando del planificador con modo, rollout, salud física, capacidad de
  frontera y decisiones del ciclo vigente;
- resumen de cámaras activas, ocupación PT y controles ambientales requeridos;
- camareros con sesión abierta, dispositivo, cámara actual y tarea vigente;
- túneles de prefrío, capacidad física, proceso activo y avance temporal;
- incidencias abiertas de carga y discrepancias de maniobra;
- alertas verificables derivadas de ocupación PT, vigencia del control ambiental,
  procesos de prefrío fuera de objetivo y conflictos de sincronización;
- conteos diarios de sincronizaciones aceptadas, pendientes, en proceso,
  rechazadas y con conflicto;
- plano editable de la planta con cámaras, túneles, andenes, bodegas y zonas,
  enriquecido con el estado operacional vigente;
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
La sincronización y sus cinco estados diarios se concentran en una franja
operacional compacta bajo el título, separada de la fecha, el turno y la acción
de actualización.

Los paneles corresponden a consultas concretas: qué decidió el árbitro, dónde
están los camareros, qué cámaras requieren atención, qué túneles están activos,
qué dispositivos están sincronizando y qué excepciones siguen abiertas. No se usan gradientes,
sombras pesadas, radios grandes ni datos de muestra. Si no existe control
ambiental, la columna de temperatura declara `SIN REGISTRO`. En escritorio los
los bloques de información se distribuyen en una grilla compacta, encabezada por
el puesto de mando a ancho completo,
con altura acotada y desplazamiento interno cuando existen más registros. En
pantallas angostas vuelven a una sola columna y recuperan su altura natural.

## Puesto de mando del planificador

El puesto de mando forma parte del mismo `GET /api/operacion-ahora`; no realiza
una segunda consulta ni reutiliza el agregado histórico del endpoint
administrativo. El `GET` consulta exclusivamente el último ciclo persistido y
su estado de vigencia: nunca ejecuta el árbitro, crea ciclos ni adquiere bloqueos
sobre maniobras. Las mutaciones operacionales solicitan el recálculo después del
commit y un job deduplicado por temporada publica la nueva proyección.

La interfaz distingue `Actual`, `Pendiente`, `Recalculando`, `Atrasado`, `Error`
y `Detenido`. Mientras llega un ciclo nuevo conserva el último confirmado, pero
lo declara no vigente y la frontera física no lo materializa. Si todavía no
existe un ciclo, los conteos muestran raya en lugar de inventar ceros.

Cada fila identifica orden y decisión, maniobra y objetivo, estado y progreso,
folio y ruta del paso actual, responsable, dispositivo, motivo y cantidad de
conflictos. El resumen conserva conteos estables cuando existe un ciclo. En
modo `off` la interfaz declara que el planificador está detenido y no presenta
un ciclo antiguo como si continuara vigente.

`Operación ahora` se expone como acceso principal del shell, por encima de los
módulos administrativos. La oficina usa una sola apariencia operacional ESTIBA;
la actualización verificable de temporada y planta se integra en la cabecera y
no compite con la navegación lateral.

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

El puesto de mando usa el mismo permiso de solo lectura de `Operación ahora` y
no amplía el acceso al endpoint administrativo de salud.


## Detalle de supervisión de maniobras

Cada decisión del ciclo vigente permite abrir un drawer lateral de solo lectura. La vista resume:

- decisión y motivo operacional;
- objetivo, prioridad, puntaje, beneficio neto, costo y riesgo;
- paso actual y progreso de la maniobra;
- camarero y tablet asignados;
- secuencia física completa;
- conflictos expresados mediante conceptos operacionales, sin mostrar UUID.

El drawer consume el mismo snapshot de `GET /api/operacion-ahora`; abrirlo no ejecuta arbitraje, no renueva reservas y no modifica maniobras. Durante el polling conserva la maniobra seleccionada y actualiza su información si continúa en el ciclo vigente.
