# Módulo de Materia prima

## Objetivo

`/oficina/materia-prima` es la oficina madre del ingreso de materia prima. Agrupa los accesos a Romana y Envases, y ejecuta la digitación que transforma los segmentos confirmados por Validación MP en lotes operacionales.

```text
Romana
→ Validación MP
→ Digitación de lotes
→ hidrocooler, cuando corresponde
→ pendiente de asignación
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
