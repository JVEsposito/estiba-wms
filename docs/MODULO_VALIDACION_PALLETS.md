# Módulo de Validación de pallets/PT

## Objetivo

Validación PT registra el nacimiento trazable de pallets y saldos de producto terminado antes de Prefrío.

```text
captura del pallet
→ Validación PT
→ folio pendiente de Prefrío
→ proceso térmico
→ habilitación para almacenamiento
→ ubicación en cámara
→ disponibilidad para carga
```

Una aprobación crea el folio de manera atómica, pero no lo ubica ni lo deja disponible para cargas. El estado inicial es `pendiente_prefrio`.

Validación PT es diferente de Validación MP. Validación MP opera recepciones de materia prima `REC-*`; Validación PT crea folios de producto terminado.

## Temporada y clientes

La captura utiliza la temporada global activa. Accesos es el único módulo que puede crear, editar, activar o migrar temporadas.

Los clientes son maestros globales. Validación mantiene por temporada:

- relación del cliente con el catálogo;
- marcas;
- categorías;
- especies;
- variedades;
- calibres;
- envases;
- CSG y variedades autorizadas.

Los cambios del maestro global no reescriben snapshots históricos de validaciones existentes.

## Catálogo jerárquico

La oficina `/oficina/validacion/catalogo` administra:

```text
Temporada
├─ clientes globales habilitados
│  └─ marcas estacionales
├─ categorías independientes
├─ especies
│  ├─ variedades
│  ├─ calibres
│  └─ envases
└─ CSG
   └─ variedades autorizadas
```

Una proyección transaccional mantiene el contrato compuesto consumido por la PDA:

- artículo = especie + variedad + calibre + envase;
- origen = cliente + marca + CSG;
- combinación = artículo + origen autorizado;
- categoría = selección independiente dentro de la temporada.

Los registros proyectados antiguos no se eliminan cuando dejan de estar autorizados; quedan inactivos para preservar referencias históricas.

## Compatibilidad de catálogo

La proyección actual considera:

- todas las combinaciones activas de variedad, calibre y envase dentro de una especie;
- todas las relaciones comerciales proyectadas entre marcas y CSG de la temporada;
- autorización de la combinación únicamente cuando el CSG admite la variedad;
- categoría activa independiente del artículo y origen.

Antes de publicar un catálogo productivo, operación debe confirmar que estas relaciones representan la realidad comercial. Restricciones adicionales por cliente, marca, categoría, envase o calibre deben modelarse explícitamente; no deben inferirse desde nombres.

## Importación CSV/XLSX

La oficina `/oficina/validacion` permite:

```text
subir planilla
→ interpretar y validar
→ previsualizar altas, cambios y errores
→ confirmar transaccionalmente
→ reconstruir la proyección para PDA
```

Reglas:

- una planilla con errores no aplica filas parciales;
- una planilla vacía no genera una importación confirmable;
- el archivo puede incluir categoría;
- los registros ausentes no se desactivan automáticamente;
- confirmar dos veces no duplica la aplicación;
- el resultado conserva archivo, checksum, usuario, filas y errores interpretados;
- XLSX requiere la extensión PHP `zip`.

El archivo original no se conserva dentro del WMS; se guarda su checksum y el contenido interpretado necesario para auditoría.

## Captura móvil

La aplicación móvil funciona en orientación vertical y permite:

- descargar el catálogo activo;
- escanear o escribir el folio;
- seleccionar pallet o saldo;
- registrar cantidad de cajas;
- seleccionar categoría;
- seleccionar artículo y origen compatibles;
- aprobar, observar o rechazar según permisos;
- consultar capturas recientes;
- conservar el último catálogo conocido;
- guardar operaciones en una bandeja local.

La bandeja se separa por usuario y dispositivo. Cada captura se persiste antes de transmitirse y conserva el mismo UUID durante los reintentos.

## Intentos e idempotencia

Cada envío utiliza `operacion_id` UUID y hash del payload.

- Repetir el mismo UUID con el mismo contenido devuelve la validación existente.
- Reutilizarlo con contenido diferente genera conflicto.
- Cada folio conserva intentos numerados.
- Observar o rechazar no crea inventario.
- Una aprobación posterior a una observación crea un nuevo intento.
- Una decisión terminal no se sustituye silenciosamente.
- Una captura basada en un catálogo anterior conserva el snapshot y marca la diferencia de versión.

La secuencia de intentos se serializa por número de folio, permitiendo que distintas PDA validen folios diferentes en paralelo.

## Resultados

### Aprobado

- crea el folio si todavía no existe;
- registra la validación como origen;
- guarda temporada y datos externos;
- establece `estado_operacional = pendiente_prefrio`;
- establece `condicion_termica = pendiente_prefrio`;
- establece `habilitacion_almacenamiento = no_habilitado`.

### Observado

- no crea folio;
- conserva motivo, observación y snapshot;
- permite una captura posterior corregida.

### Rechazado

- no crea folio;
- exige motivo;
- constituye una decisión terminal según las capacidades y confirmación definidas.

## Paso a Prefrío

El folio aprobado aparece como candidato para Prefrío. Durante el proceso térmico cambia su condición y, al aprobarse, queda habilitado para almacenamiento.

La aprobación de Prefrío no crea la ubicación. Cámaras consulta el folio, recupera los datos nacidos en Validación y permite la primera ubicación cuando:

```text
estado_operacional = pendiente_prefrio
condicion_termica = prefrio_aprobado
habilitacion_almacenamiento = habilitado
```

Después de esa ubicación el folio pasa a `disponible`.

## Roles

### `validador`

Puede:

- descargar catálogos;
- capturar aprobaciones y observaciones;
- consultar validaciones;
- operar la bandeja móvil.

No opera Cámaras, Cargas, Materiales, Romana ni Validación MP.

### `supervisor_frio`

Puede consultar y ejecutar las acciones de validación habilitadas, además de confirmar decisiones terminales según la política del módulo.

### `administrador`

Conserva acceso total y administra el catálogo desde oficina.

## API

### Captura y consulta

```http
GET  /api/validacion/catalogos
GET  /api/validacion/pallets
GET  /api/validacion/pallets/{id}
POST /api/validacion/pallets
```

### Administración

Las rutas bajo `/api/administracion/validacion/*` permiten consultar el catálogo, mantener sus entidades, importar archivos y confirmar importaciones. Todas requieren la capacidad administrativa correspondiente.

## Payload base

```json
{
  "operacion_id": "uuid",
  "numero_folio": "10293847",
  "tipo_bulto": "pallet",
  "cantidad_cajas": 120,
  "temporada_id": "uuid",
  "catalogo_version": 12,
  "categoria_validacion_id": "uuid",
  "articulo_validacion_id": "uuid",
  "origen_validacion_id": "uuid",
  "resultado": "aprobado",
  "motivo": null,
  "observacion": null,
  "generado_dispositivo_at": "2026-07-23T11:42:00-04:00"
}
```

## Interfaces implementadas

- Oficina principal para historial, métricas e importaciones.
- Oficina jerárquica para administración del catálogo.
- Aplicación móvil vertical.
- Catálogo local y bandeja offline.
- Integración completa con el nacimiento del folio y el flujo de Prefrío.

## Usuario local

En `local` y `testing`:

- usuario: `validador@estiba.local`;
- contraseña: `password`;
- dispositivo: `TABLET-01`.

## Pendientes

- matriz comercial más específica si negocio restringe combinaciones actualmente proyectadas;
- evidencia fotográfica;
- integración ERP;
- procedimiento administrado para reabrir una decisión terminal cuando exista una causa autorizada.
