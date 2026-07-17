# PR #29 — Oficina de despachos frigoríficos

## Propósito

Extender `/oficina/cargas` para que el despachador gestione el ciclo documental y
comercial de una carga frigorífica sin mezclar la operación física de la APK.

## Flujo cubierto

1. Crear un borrador, indicar orden externa, prioridad, cámara objetivo y andén previsto.
2. Buscar y seleccionar folios disponibles de manera paginada.
3. Publicar la carga para crear las tareas compartidas del equipo de frío.
4. Consultar avance, distribución, tareas, concentración e incidencias.
5. Resolver incidencias mediante despacho parcial, reemplazo equivalente o reparación.
6. Confirmar la salida del camión cuando todos los folios se encuentren en andén.

## Concentración

La concentración no es un estado manual. Se calcula con los folios vigentes de la carga:

- se forma un grafo independiente por cámara y nivel;
- dos folios son vecinos cuando ocupan posiciones correlativas en una banda;
- también son vecinos cuando están en bandas consecutivas y a igual profundidad o una adyacente;
- el grupo conectado más grande representa la concentración principal;
- los folios que ya están en andén también cuentan como concentrados;
- el umbral informativo es 80 % y nunca bloquea una operación.

La respuesta API informa porcentaje, concentrados, faltantes, folios en andén,
incidencias y la ubicación del grupo principal.

## Incidencias

La oficina dispone de un filtro global para cargas con alertas abiertas y de un panel
por carga. Cada alerta conserva folio, ubicación reportada, camarero, dispositivo y hora.

Resoluciones:

- `despacho_parcial`: descarta el folio de la carga y libera su reserva;
- `reemplazo`: exige un folio activo, disponible, ubicado y equivalente en tipo,
  condición SAG, variedad, calibre, marca y exportadora;
- `reparado`: devuelve el folio a la cola operacional.

Los UUID de resolución y cierre preservan la idempotencia definida en el PR #28.

## API agregada o ampliada

- `GET /api/cargas/incidencias`
  - filtros: `estado`, `carga_id`, `page`, `per_page`;
- `GET /api/cargas`
  - filtro nuevo: `solo_con_incidencias=1`;
- `GET /api/cargas/folios-disponibles`
  - filtro nuevo: `equivalente_a={folio_uuid}`;
- recursos de cargas
  - progreso de concentración, incidencias abiertas, tareas y folios históricos de cargas cerradas;
- acceso de oficina
  - capacidades explícitas de resolución y cierre.

## Fuera de alcance

- bandeja, ruta vertical e incidencias desde la APK: PR #30;
- notificaciones persistentes y polling: PR #31;
- integraciones con ERP o transporte externo.
