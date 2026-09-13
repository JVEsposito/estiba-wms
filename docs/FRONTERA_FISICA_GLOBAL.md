# Frontera física global

La frontera física global une el arbitraje de maniobras con la materialización
del próximo destino. La tablet ya no calcula una frontera separada por plan:
trabaja sobre una sola fotografía autoritativa de las maniobras que el camarero
tiene tomadas, aunque provengan de objetivos operacionales distintos.

## Contrato operacional

- El servidor arbitra globalmente las maniobras antes de publicar destinos.
- La tablet simula y propone; no bloquea posiciones por sí sola.
- Solo el paso actual de cada maniobra puede recibir una reserva física.
- `WMS_PLANNER_FRONTIER_MAX` limita maniobras independientes, no los pasos
  internos de una maniobra.
- Un pallet o una posición no pueden participar en dos maniobras activas.
- La aceptación puede ser parcial: una propuesta válida no se pierde porque
  otra haya quedado obsoleta o compita por el mismo destino.
- Después de `RETIRAR PALLET`, la tarea queda `en_proceso`; su destino no puede
  cambiar y su reserva no puede liberarse por vencimiento.

## Snapshot global

`GET /api/frontera-fisica/snapshot` devuelve una fotografía específica para el
usuario y dispositivo autenticados. Incluye:

- versión SHA-256 del estado físico;
- ciclo y resumen del arbitraje global;
- configuración efectiva del planificador;
- versiones de plano y revisiones de reservas de todas las cámaras activas de
  producto terminado;
- paso actual tomado de cada maniobra, con versiones de tarea, plan, maniobra y
  reserva;
- decisión de arbitraje y bandera `materializable`.

La versión cambia cuando cambia una cámara, una reserva, una tarea, su plan o el
ciclo de arbitraje. Un snapshot obsoleto se rechaza completo antes de reservar
la primera posición.

## Materialización

`POST /api/frontera-fisica/materializar` recibe hasta el máximo configurado de
propuestas. Cada propuesta identifica:

- tarea y posición destino;
- versión de la tarea y de su propio plan;
- versión conocida del plano de la cámara;
- versión del planificador, puntaje y motivo calculados por la tablet.

El servidor vuelve a comprobar propiedad del claim, secuencia actual, arbitraje,
rollout, versiones, compatibilidad física, ocupación y reservas. Las propuestas
se procesan en orden y devuelven `aceptadas`, `rechazadas`, `recalcular` y un
nuevo snapshot global.

## Replanificación segura

Al terminar un paso, el servidor avanza la misma maniobra y la tablet vuelve a
consultar la frontera global. Solo se recalcula el sufijo todavía no iniciado.
Las extracciones temporales continúan bajo custodia explícita y conservan su
retorno obligatorio; nunca se crean posiciones ficticias para representar un
pallet retirado.

## Compatibilidad

Los endpoints por plan de `GET /api/planes-operacionales/{plan}/snapshot` y
`POST /api/planes-operacionales/{plan}/frontera` permanecen disponibles para
clientes anteriores. La bandeja actual usa la frontera física global. En modos
`off`, `shadow`, `server` o `batch` no se habilita la materialización global.
