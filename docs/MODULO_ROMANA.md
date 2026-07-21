# Módulo de Romana

## Objetivo

Romana es el punto de inicio contractual de la cadena de recepción. El registro
cerrado representa lo que ingresó legalmente al frigorífico y conserva el
cliente, la guía, el transporte, los envases declarados y los pesos observados.

La oficina se encuentra en `/oficina/romana` y utiliza el mismo acceso Sanctum
de los demás módulos de oficina.

## Límite operacional con Frigorífico

Romana y Frigorífico son flujos independientes. Una recepción de Romana no crea,
habilita ni mueve registros de `folios`, `validaciones_pallet`, Prefrío o Cámaras.
El correlativo `REC-*` identifica exclusivamente el expediente contractual de
Romana; no es un folio de Frigorífico.

Cuando se incorporen lotes y folios propios de Romana vivirán en tablas y
máquinas de estado del dominio Romana. La trazabilidad futura entre ambos flujos
se implementará como una asociación explícita, sin reutilizar identificadores ni
convertir el cierre de Romana en una transición de Frigorífico.

## Temporada global

`temporadas` es la dimensión operacional compartida. Validación usa la temporada
directamente; la configuración estacional de Materiales se vincula mediante
`temporadas_materiales.temporada_id`; los folios de Frigorífico guardan su
`temporada_id` cuando el origen permite determinarla; y cada recepción de Romana
exige la temporada global activa.

Compartir temporada permite agrupar y comparar información sin unir los ciclos
de vida de Romana, Materiales y Frigorífico. La recepción conserva además código
y nombre de temporada como snapshot contractual.

## Cliente operacional transversal

La recepción se relaciona con el maestro global `clientes`, no con un catálogo
estacional específico. `clientes_validacion` y `clientes_materiales` conservan
su configuración por temporada y apuntan al mismo maestro mediante `cliente_id`.
Las altas, ediciones, importaciones y copias de temporada mantienen ese vínculo.

Romana puede recibir cualquier cliente operacional activo, esté presente en
Validación, Materiales o ambos flujos. Al crear la recepción guarda nombre y
código como snapshot contractual; un cambio posterior de catálogo no altera el
Aviso de Recibo histórico.

## Estados

| Estado | Significado | Acciones permitidas |
|---|---|---|
| `en_bascula_ingreso` | El camión cargado fue pesado y sus documentos están en revisión. | Editar antecedentes o confirmar ingreso. |
| `en_bascula_salida` | El bruto quedó confirmado y el camión debe volver vacío. | Registrar tara y cerrar. |
| `cerrado` | La tara fue capturada, el neto calculado y el correlativo asignado. | Consultar y descargar PDF. |

No se permite volver a estados anteriores ni editar una recepción después de
confirmar el ingreso.

## Datos transaccionales

La tabla `recepciones_romana` contiene:

- timestamps de ingreso, confirmación y salida;
- patente de camión y patente opcional de carro;
- RUT y nombre del conductor;
- cliente y snapshot contractual del catálogo;
- temporada global y snapshot contractual de código y nombre;
- tipo de servicio (`almacenaje`, `proceso`, `prefrio`);
- cantidad y tipo de envase declarado (`bins`, `totes`, `cajas`);
- número de guía de despacho;
- peso bruto, tara y neto en kilogramos con dos decimales;
- estado, versión, usuarios responsables y observaciones separadas de ingreso y cierre;
- `operacion_id` y hash para idempotencia.

La guía no puede repetirse para el mismo cliente dentro de la misma temporada.
La tara debe ser positiva y menor al bruto. El RUT se normaliza y valida con
módulo 11.

## Correlativo

El número se asigna únicamente al cerrar, dentro de la misma transacción que
persiste la tara y el neto. Su formato es `REC-AAMM-####`; por ejemplo,
`REC-2607-0001`.

`correlativos_recepcion_romana` mantiene una secuencia por mes y se bloquea al
incrementar para impedir duplicados bajo concurrencia.

## Trazabilidad

`eventos_recepcion_romana` registra:

- `ingreso_registrado`;
- `ingreso_actualizado`;
- `ingreso_confirmado`;
- `recepcion_cerrada`.

Cada evento identifica usuario, hora, transición, datos relevantes y operación
idempotente. Los modelos impiden eliminación física.

## Aviso de Recibo

Una recepción cerrada expone un PDF descargable con:

- número de recepción y horas de entrada/salida;
- cliente, servicio y guía;
- envases declarados;
- camión, carro y conductor;
- bruto, tara y peso neto destacado;
- observación y espacios de firma.

El endpoint rechaza recepciones todavía abiertas.

## Integración gerencial

`/oficina/gerencia` incorpora en su lectura automática:

- camiones en ingreso y pendientes de destare;
- recepciones cerradas, clientes y envases del día;
- peso neto recibido hoy;
- tendencia gráfica de peso neto y recepciones de los últimos siete días;
- alerta operacional cuando existen camiones pendientes de pesaje de salida.

## API

Todas las rutas requieren `auth:sanctum`.

### Consulta

- `GET /api/romana/catalogos`
- `GET /api/romana/recepciones`
- `GET /api/romana/recepciones/{id}`
- `GET /api/romana/recepciones/{id}/aviso-recibo`

### Operación

- `POST /api/romana/recepciones`
- `PUT /api/romana/recepciones/{id}`
- `POST /api/romana/recepciones/{id}/confirmar-ingreso`
- `POST /api/romana/recepciones/{id}/cerrar`

## Roles

| Rol | Consulta | Operación |
|---|---:|---:|
| `administrador` | Sí | Sí |
| `supervisor_frio` | Sí | Sí |
| `operador_romana` | Sí | Sí |
| `despachador` | Sí | No |
| `consulta` | Sí | No |

En local/testing el seeder crea `romana@estiba.local` con contraseña
`password`.
