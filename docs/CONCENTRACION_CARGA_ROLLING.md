# Concentración rolling de cargas

## Objetivo

El planificador puede transformar una carga publicada y dispersa en un objetivo operacional `concentracion_carga` sin convertir el orden visual de la cámara en un fin por sí mismo.

La regla central es:

> Mover solamente cuando el movimiento aumenta de forma demostrable la concentración física de la carga o despeja el acceso imprescindible para conseguirlo.

## Definición de carga junta

Se reutiliza la métrica existente de `CalculadorConcentracionCarga`.

Una carga cumple el objetivo cuando al menos el 80 % de sus pallets completos vigentes están:

- en andén; o
- dentro del componente físico conectado más grande de una misma cámara y nivel.

Dos posiciones forman parte del mismo componente si:

- están en la misma banda y sus profundidades son consecutivas; o
- están en bandas consecutivas y su profundidad es igual o adyacente.

El planificador y la API usan la misma definición; no existe un segundo concepto de concentración para las tareas rolling.

## Universo del porcentaje

El denominador incluye pallets completos vigentes de la carga en estado:

- `pendiente`;
- `con_incidencia`;
- `en_anden`.

Un pallet pendiente que todavía no tenga ubicación física sigue contando en el total, aunque no pueda convertirse todavía en candidato de movimiento.

Esto evita declarar una carga concentrada mientras aún faltan pallets por incorporarse físicamente desde otros procesos.

## Cámara objetivo

La cámara objetivo se determina en este orden:

1. `carga.camara_objetivo_id`, si fue definida explícitamente;
2. cámara del grupo físico principal ya existente.

Si no existe ninguna de las dos señales, el planificador no inventa una cámara objetivo.

Cuando existe una cámara objetivo explícita pero todavía no hay un grupo dentro de ella, la frontera publica como máximo un pallet semilla. Después de ejecutarlo se recalcula la geometría antes de publicar más trabajo.

## Frontera corta

La cantidad de tareas activas queda limitada por:

```text
min(WMS_PLANNER_FRONTIER_MAX, movimientos mínimos necesarios para alcanzar 80 %)
```

Ejemplo:

```text
10 pallets
7 concentrados
objetivo = 8

→ se publica como máximo 1 movimiento de concentración
```

No se publican tres movimientos solo porque existan tres pallets dispersos.

## Selección de candidatos

Primero se consideran pallets de la propia carga que:

- estén pendientes;
- posean ubicación física;
- estén fuera del grupo principal;
- sean físicamente accesibles.

Si existe al menos un candidato directo, se prefiere sobre cualquier despeje.

Solo cuando no existe un pallet directo accesible puede publicarse un `despeje_concentracion` para retirar el bloqueador exterior necesario.

No se desplaza como despeje:

- un pallet perteneciente al grupo principal; ni
- un pallet comprometido activamente con otra carga.

## Destino calculado en tablet

Las tareas `concentrar_carga` nacen con cámara objetivo, pero sin posición exacta.

La tablet solo puede proponer una posición que:

- esté activa y libre;
- pertenezca a una banda operativa con uso `transito_pt`;
- pertenezca a la cámara objetivo;
- toque físicamente el componente principal con la misma regla de vecindad usada por la métrica.

La puntuación favorece posiciones que tengan más contactos con el grupo principal.

El servidor vuelve a validar la misma geometría antes de materializar la reserva física. Una propuesta que no aumente el componente es rechazada.

## Despejes

`despeje_concentracion` conserva el origen físico, pero deja abierto el tipo definitivo hasta materializar destino:

- destino en la misma cámara → `reubicacion`;
- destino en otra cámara → `traslado_entre_camaras`.

La tablet propone; el servidor normaliza el tipo, valida y reserva.

## Prioridad de despacho directo

La presencia de un camión en andén domina la concentración.

Cuando existe una presencia activa:

- no se publica nuevo trabajo de concentración;
- las tareas `pendiente` o `asumida` de concentración se cancelan de forma reversible;
- una tarea `en_proceso` conserva su destino y termina normalmente.

Cuando el camión deja el andén, la carga vuelve a evaluarse desde su estado físico real.

## Condición de término

Si el porcentaje llega a 80 %:

- se cancelan tareas reversibles que hayan dejado de ser necesarias;
- el plan se completa cuando no queda ninguna tarea físicamente `en_proceso`.

Si posteriormente la carga sigue operativa y vuelve a quedar bajo el umbral, el mismo objetivo persistente puede reactivarse manteniendo trazabilidad.

## Recálculo reactivo

La concentración se recalcula después de publicar cambios relevantes de carga y después del commit físico de una ubicación.

El recálculo por movimiento se limita a las cargas afectadas por:

- el folio movido;
- la banda/nivel de origen;
- la banda/nivel de destino.

No se recorren todas las cargas de la temporada después de cada movimiento.

## Compatibilidad

El flujo histórico `TareaCarga` permanece disponible durante la transición. `concentracion_carga` no lo elimina ni cambia su contrato.

El nuevo objetivo solo dirige trabajo cuando la configuración corresponde a cálculo rolling en tablet. `off`, `shadow` y configuraciones no dirigidas mantienen sus contratos de rollback/auditoría.
