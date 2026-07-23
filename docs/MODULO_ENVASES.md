# Módulo de Envases

## Objetivo

El dominio de Envases controla existencia y cuenta corriente de bins, totes y esponjas asociados a clientes, propiedad y temporada. Sus movimientos se originan principalmente en Romana, Validación MP y guías internas de despacho.

```text
recepción / compra / arriendo
→ movimiento de existencia y cuenta corriente
→ revisión
→ disponibilidad
→ guía interna GDE-* en borrador
→ reserva FIFO de existencia
→ confirmación o cancelación
→ eventual anulación compensatoria
```

Envases es un dominio independiente de los folios de Frigorífico y del inventario de Materiales.

## Interfaces

```text
/oficina/envases/cuenta-corriente
/oficina/envases/despachos
```

La primera oficina consulta movimientos confirmados y reservas. La segunda crea, edita, cancela, confirma y anula guías internas de salida, además de generar sus respaldos PDF.

## Tipos de envase

El alcance actual trata como líneas independientes:

- bins;
- totes;
- esponjas.

No se agregan cantidades de tipos diferentes como una única unidad.

## Propiedad

Cada movimiento o línea identifica su régimen de propiedad:

### Propia

El envase pertenece a la empresa operadora.

### Del cliente

El envase pertenece al cliente relacionado con la recepción o despacho.

### Arrendada

El envase pertenece a un tercero o arrendador. Su cuenta debe conservarse aunque el envase se despache hacia otro cliente.

La propiedad determina qué trazabilidad de origen es obligatoria y qué cuenta se afecta.

## Cuenta corriente

La cuenta corriente se explica mediante movimientos firmados.

Cada movimiento conserva, según corresponda:

- temporada;
- cliente;
- tipo de envase;
- cantidad con signo;
- propiedad;
- origen o documento relacionado;
- fecha y hora;
- usuario;
- estado de revisión;
- observación.

Un saldo nunca se corrige eliminando un movimiento anterior. Las anulaciones y correcciones se realizan mediante nuevos asientos compensatorios.

## Orígenes de movimientos

Pueden existir movimientos asociados a:

- recepción de fruta con envases;
- recepción exclusiva de envases;
- compra;
- arriendo;
- confirmación real de Validación MP;
- guía de despacho;
- anulación de guía;
- ajustes expresamente autorizados.

Romana conserva la cantidad declarada. Validación MP confirma la cantidad real y deja visible la diferencia.

## Revisión

Los movimientos pueden ser revisados u observados por perfiles autorizados.

La revisión:

- no elimina el movimiento;
- identifica al revisor;
- conserva fecha y observación;
- permite distinguir un asiento verificado de uno pendiente o cuestionado.

## Guía interna de despacho

La salida se documenta mediante:

```text
GDE-AAMM-NNNN
```

La guía registra:

- temporada;
- cliente destino;
- fecha y hora de salida;
- líneas de bins, totes y esponjas;
- cantidad;
- propiedad;
- movimiento o saldo de origen cuando corresponde;
- observación;
- usuarios responsables.

La guía es un documento operacional interno. No debe presentarse como factura, guía tributaria o DTE legal.

## Estados de la guía

```text
borrador
confirmada
cancelada
anulada
```

### Borrador

- permite preparar, revisar y editar las líneas;
- asigna orígenes FIFO y reserva disponibilidad;
- no modifica todavía la existencia física ni la cuenta corriente;
- puede cancelarse sin generar movimientos.

### Confirmada

- descuenta la existencia física;
- genera el movimiento negativo de cuenta corriente;
- queda inmutable;
- conserva la hora exacta de salida.

### Cancelada

- libera la reserva del borrador;
- conserva el motivo, usuario y fecha;
- no genera movimientos de existencia ni cuenta corriente.

### Anulada

- conserva la guía confirmada y su historia;
- genera movimientos compensatorios;
- no borra ni edita los asientos originales.

## Reglas de stock

1. No se confirma una cantidad superior a la existencia compatible.
2. Las líneas duplicadas incompatibles se rechazan.
3. El consumo conjunto de varias líneas no puede exceder el saldo.
4. La propiedad y el movimiento de origen deben coincidir con la línea.
5. Una guía histórica no se confirma ni anula desde otra temporada activa.
6. Una anulación no puede duplicar la devolución del mismo movimiento.

## Idempotencia

La creación utiliza UUID y hash del payload. Las transiciones posteriores se serializan mediante bloqueo transaccional y estado terminal.

- mismo UUID y mismo contenido al crear: resultado existente;
- mismo UUID y contenido diferente: conflicto;
- doble confirmación: no duplica el descuento;
- doble cancelación: no modifica nuevamente la reserva;
- doble anulación: no duplica la compensación.

La oficina incorpora un fallback UUID v4 basado en `crypto.getRandomValues()` para navegadores de red local que no exponen `crypto.randomUUID()`.

## Temporada y cliente

- Las guías y movimientos conservan su temporada.
- La operación vigente se filtra por la temporada global activa.
- El historial de otra temporada permanece consultable, pero no se modifica desde el ciclo actual.
- Los clientes provienen del maestro global administrado desde Accesos.
- Los documentos guardan la referencia y snapshots necesarios para conservar la historia.

## API

### Cuenta corriente

```http
GET  /api/envases/cuenta-corriente/catalogos
GET  /api/envases/cuenta-corriente/movimientos
POST /api/envases/cuenta-corriente/movimientos/{movimiento}/revisar
```

### Guías de despacho

```http
GET  /api/envases/guias-despacho/catalogos
GET  /api/envases/guias-despacho
GET  /api/envases/guias-despacho/{guia}
GET  /api/envases/guias-despacho/{guia}/documento
GET  /api/envases/guias-despacho/{guia}/comprobante-anulacion
POST /api/envases/guias-despacho
PUT  /api/envases/guias-despacho/{guia}
POST /api/envases/guias-despacho/{guia}/confirmar
POST /api/envases/guias-despacho/{guia}/cancelar
POST /api/envases/guias-despacho/{guia}/anular
```

Todas las rutas requieren `auth:sanctum` y el Gate específico de consulta, revisión o gestión.

## Auditoría

El sistema debe poder reconstruir:

- saldo anterior;
- movimiento aplicado;
- saldo resultante;
- documento de origen;
- cliente y temporada;
- propiedad del envase;
- actor y fecha;
- revisión;
- compensaciones posteriores.

Los movimientos, eventos y guías no se eliminan físicamente. Las guías confirmadas antes del versionado documental se reconstruyen una vez, se marcan expresamente como históricas y conservan desde entonces su snapshot y hash.

## Límites actuales

- Las guías no son DTE legales.
- No existe integración automática con contabilidad o ERP.
- No existe telemetría automática de conteo.
- Las oficinas dependen de conexión con el servidor.
- Los catálogos de tipos de envase permanecen acotados al alcance implementado.

## Pendientes

- integración documental o tributaria cuando corresponda;
- conciliaciones y cierres periódicos;
- reportes históricos avanzados;
- recuperación auditada de diferencias operacionales;
- integración ERP;
- eventual operación móvil u offline.
