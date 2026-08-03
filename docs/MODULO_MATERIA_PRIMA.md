# Módulo de Materia prima

## Objetivo

`/oficina/materia-prima` es la oficina madre del ingreso de materia prima. Agrupa Romana, Digitación, Fruta a proceso y Envases. Digitación transforma los segmentos confirmados por Validación MP en lotes operacionales y Fruta a proceso controla la entrega física hacia Packing y el retorno clasificado a cámara.

```text
Romana
→ Validación MP
→ Digitación de lotes
→ hidrocooler, cuando corresponde
→ pendiente de asignación
→ cámara de materia prima
→ entregas parciales de bins a Packing
→ retornos de Packing por resultado
→ sublotes internos pendientes de ubicación
→ cámara de materia prima
```

El lote no es un folio ni un pallet de Frigorífico.

## Peso neto por envase

Al cerrar una recepción, Romana selecciona uno de los tipos de envase declarados y calcula:

```text
peso neto recepción = peso bruto - tara
peso neto por envase = peso neto recepción / cantidad del envase seleccionado
peso neto calculado del lote = peso neto por envase × envases primarios del lote
```

Digitación recibe el valor calculado, debe confirmarlo y puede corregirlo. Se conservan ambos valores y se informa si existió corrección.

La suma de kilos netos de los lotes vigentes no puede superar el peso neto de Romana.

## División en lotes

Un segmento puede producir uno o varios lotes. La división puede responder a diferencias de variedad, CSG, cuartel o GGN.

Cada lote registra:

- número manual de lote;
- CSG;
- SdP numérico manual y obligatorio;
- GGN manual, numérico, obligatorio y de 13 dígitos;
- fecha de cosecha;
- predio;
- especie, variedad, calibre y cuartel;
- tipo de producto: materia prima, comercial, precalibre o descarte;
- envase primario y cantidad;
- envase secundario y cantidad, cuando corresponda;
- kilos brutos;
- kilos netos calculados;
- kilos netos confirmados;
- decisión de hidrocooler;
- observación.

Los envases asignados entre borradores y lotes vigentes no pueden superar lo confirmado en el segmento.

## Número de lote

El número se escribe manualmente según la información de la exportadora. Debe ser único entre lotes vigentes para la combinación temporada y cliente.

Una anulación supervisada libera la clave, de modo que el número pueda recrearse con los antecedentes correctos sin eliminar el registro anterior.

## Estados

| Estado | Significado |
|---|---|
| `borrador` | Antecedentes editables, todavía no confirmados. |
| `pendiente_hidrocooler` | Lote confirmado que requiere tratamiento. |
| `hidrocooler_en_curso` | Tratamiento iniciado. |
| `pendiente_asignacion` | No requiere hidrocooler o ya lo completó; puede pasar a cámara. |
| `asignado_camara` | Tiene una asignación de destino a cámara de materia prima. |
| `entrega_parcial_proceso` | Packing recibió una parte de los bins y el lote conserva saldo en cámara. |
| `entregado_proceso` | Todos los bins del lote fueron entregados a Packing. |
| `anulado` | Corrección supervisada conservada en el historial. |

Confirmar un lote actualiza el segmento a `lotizacion_parcial` o `lotizado` según la distribución confirmada de sus envases.

## Hidrocooler

Cuando el lote lo requiere:

1. se registra equipo e inicio;
2. el usuario autenticado queda como operador de inicio;
3. al terminar se registra término, temperatura y observación;
4. la duración se calcula automáticamente en minutos;
5. el usuario autenticado queda como operador de término;
6. el lote pasa a `pendiente_asignacion`.

Un lote que no requiere hidrocooler pasa directamente a `pendiente_asignacion`.

## Cámara

`materia_prima` es un contenido de cámara independiente de `productos` y `materiales`.

La asignación del lote:

- exige una cámara activa de contenido `materia_prima`;
- registra usuario, fecha y observación;
- se realiza a nivel de cámara;
- no crea folio;
- no ocupa una posición del plano frigorífico.

## Fruta a proceso

La oficina `/oficina/materia-prima/fruta-a-proceso` y el módulo tablet `fruta_proceso` muestran únicamente lotes cuyo envase primario es `bins`, pertenecen a la temporada activa y ya están asignados a una cámara de materia prima.

El camarero registra una entrega por cada viaje físico. No se escanea cada bin. Cada movimiento exige:

- cantidad de bins del viaje;
- línea de proceso;
- turno A o B;
- número de orden de Packing;
- observación opcional;
- kilos enviados opcionales para calcular rendimiento y merma cuando Packing también informa kilos recuperados.

La cantidad se descuenta del saldo vigente con bloqueo transaccional y nunca puede superar los bins disponibles. Cada solicitud utiliza un UUID idempotente para impedir duplicados por reintentos de red.

Un camarero puede anular solamente su última entrega mientras el lote todavía tenga saldo y antes de que Packing registre un retorno. Un supervisor de frío o administrador puede corregir cualquier entrega con motivo obligatorio mientras no tenga retornos. La corrección no borra el viaje: lo marca anulado, restituye el saldo y conserva operador, dispositivo, fecha y motivo.

### Retornos de Packing

Cada retorno se registra contra una entrega física concreta. Puede ser parcial o cerrar definitivamente esa entrega y admite varios resultados en una misma operación:

- precalibre;
- comercial;
- descarte;
- otro resultado con nombre manual.

Cada resultado crea un sublote interno correlativo (`PC-`, `CO-`, `DE-` u `OT-`) vinculado al lote original, la recepción y la entrega. Los bins de salida pueden diferir de los enviados porque Packing puede vaciar y volver a llenar envases. Los kilos recuperados son opcionales; si la entrega y sus resultados informan kilos, al cerrar se calcula la merma.

Los sublotes nacen en `pendiente_ubicacion`. El camarero los asigna a una cámara activa exclusiva de materia prima, sin crear una recepción nueva ni duplicar la fruta. Un retorno puede anularse con motivo solamente antes de ubicar cualquiera de sus sublotes; la anulación conserva toda la trazabilidad y excluye sus cantidades de los totales vigentes.

## Correcciones

Los borradores pueden editarse con control de versión.

Un administrador o supervisor de frío puede anular un lote mientras no tenga ejecución física de hidrocooler ni asignación a cámara. La anulación:

- exige un motivo;
- conserva eventos y antecedentes;
- libera envases y kilos;
- recalcula el estado del segmento;
- permite recrear el mismo número manual.

## API

### Consulta

```http
GET /api/materia-prima/resumen
GET /api/materia-prima/catalogos
GET /api/materia-prima/segmentos-pendientes
GET /api/materia-prima/lotes
GET /api/materia-prima/lotes/{lote}
```

### Operación

```http
POST /api/materia-prima/lotes
PUT  /api/materia-prima/lotes/{lote}
POST /api/materia-prima/lotes/{lote}/confirmar
POST /api/materia-prima/lotes/{lote}/hidrocooler/iniciar
POST /api/materia-prima/lotes/{lote}/hidrocooler/completar
POST /api/materia-prima/lotes/{lote}/asignar-camara
POST /api/materia-prima/lotes/{lote}/anular

GET  /api/materia-prima/fruta-proceso/resumen
GET  /api/materia-prima/fruta-proceso/catalogos
GET  /api/materia-prima/fruta-proceso/lotes
GET  /api/materia-prima/fruta-proceso/lotes/{lote}
POST /api/materia-prima/fruta-proceso/lotes/{lote}/entregas
POST /api/materia-prima/fruta-proceso/entregas/{entrega}/anular
POST /api/materia-prima/fruta-proceso/entregas/{entrega}/retornos
POST /api/materia-prima/fruta-proceso/retornos/{retorno}/anular
POST /api/materia-prima/fruta-proceso/sublotes/{sublote}/ubicar
```

Todas las mutaciones usan UUID de operación. Las transiciones y correcciones generan eventos auditables.

## Rol

`digitador_materia_prima` puede:

- consultar Romana y la cuenta de Envases;
- consultar cámaras de materia prima;
- crear y editar borradores;
- confirmar lotes;
- iniciar y completar hidrocooler;
- asignar lotes a cámara.

No puede anular lotes confirmados ni operar posiciones de cámara.

`camarero_frio` puede consultar Fruta a proceso, registrar viajes y retornos, y ubicar sus sublotes. `supervisor_frio` y `administrador` pueden además realizar correcciones supervisadas. Los perfiles configurables deben tener habilitados tanto `materia-prima.fruta-proceso` como el módulo tablet `fruta_proceso` cuando corresponda usar la APK.
