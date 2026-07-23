# Módulo de Romana

## Objetivo

Romana es el punto de inicio contractual de la recepción. Registra lo que ingresó físicamente a la planta y conserva cliente, temporada, guía, transporte, envases declarados y pesos observados.

La oficina se encuentra en:

```text
/oficina/romana
```

## Separación respecto de Frigorífico

Romana y Frigorífico son dominios independientes.

Una recepción de Romana no crea, habilita ni mueve:

- folios;
- validaciones de pallets/PT;
- procesos de Prefrío;
- posiciones de Cámara;
- cargas de producto.

El correlativo `REC-*` identifica exclusivamente el expediente contractual de recepción. No es un folio ni un número de lote.

La trazabilidad futura con lotes o procesos posteriores debe implementarse mediante asociaciones explícitas, sin reutilizar identificadores ni convertir el cierre de Romana en una transición de Frigorífico.

## Temporada global

Cada recepción exige la temporada global activa y guarda:

- `temporada_id`;
- código de temporada como snapshot;
- nombre de temporada como snapshot.

Accesos es el único módulo que crea, edita, activa o migra temporadas.

Una recepción histórica conserva su temporada aunque posteriormente se active otro ciclo. La migración de temporada no copia ni transforma recepciones de Romana.

## Cliente global

La recepción se relaciona con el maestro global `clientes`.

Romana puede recibir cualquier cliente operacional activo. Al crear la recepción conserva código y nombre como snapshot contractual, por lo que una modificación posterior del maestro no altera el expediente ni el Aviso de Recibo.

## Correlativo

El número de recepción se asigna al crear el expediente, dentro de la misma transacción que registra el peso bruto.

Formato:

```text
REC-AAMM-####
```

Ejemplo:

```text
REC-2607-0001
```

La secuencia es mensual y se bloquea al incrementarse para impedir duplicados bajo concurrencia.

Asignar el correlativo desde el inicio permite que Validación MP busque, pistolee y tome la recepción antes de que el camión regrese a destare.

## Estados de pesaje

| Estado | Significado | Acciones permitidas |
|---|---|---|
| `en_bascula_ingreso` | Se registró el bruto y los antecedentes pueden revisarse. | Editar datos permitidos o confirmar ingreso. |
| `en_bascula_salida` | El ingreso fue confirmado y el camión debe volver vacío. | Registrar tara y cerrar. |
| `cerrado` | Se capturó la tara y se calculó el peso neto. | Consultar y descargar PDF. |

No se permite volver a un estado anterior. Después de confirmar el ingreso se congelan los antecedentes contractuales definidos por el dominio.

El estado de pesaje es independiente del avance de Validación MP. Una recepción puede estar cerrada en Romana y continuar pendiente, tomada o confirmada en el flujo de Validación MP.

## Datos transaccionales

La recepción registra:

- número `REC-*`;
- temporada y snapshots;
- cliente y snapshots;
- fecha y hora de ingreso;
- patente de camión;
- patente opcional de carro;
- RUT y nombre del conductor;
- tipo de servicio;
- número de guía;
- peso bruto;
- tara;
- peso neto;
- observaciones de ingreso y cierre;
- versión;
- usuarios responsables;
- UUID y hash de payload para idempotencia.

Los envases se almacenan como líneas independientes, entre ellas:

- bins;
- totes;
- esponjas.

La recepción puede corresponder a fruta con envases o a una operación exclusiva de envases por compra o arriendo.

La guía no puede repetirse para el mismo cliente dentro de la misma temporada. El RUT se normaliza y valida con módulo 11. La tara debe ser positiva y menor al bruto.

## Flujo

```text
crear recepción y asignar REC-*
→ revisar antecedentes
→ confirmar ingreso
→ retorno del camión vacío
→ registrar tara
→ calcular peso neto
→ cerrar
→ emitir Aviso de Recibo
```

Crear o cerrar Romana no genera automáticamente inventario frigorífico.

## Validación MP

Las recepciones elegibles se publican al rol `validador_mp` mediante una bandeja operacional.

Validación MP:

- busca o pistolea `REC-*`;
- toma la recepción de forma exclusiva;
- hereda cliente, temporada, guía y transporte;
- compara envases declarados con cantidades reales;
- revisa visualmente tarjas;
- crea segregaciones provisionales;
- confirma el movimiento real de envases.

Los segmentos resultantes permanecen `pendiente_lote`. El módulo todavía no genera el lote definitivo.

## Cuenta corriente de envases

Los envases declarados y luego confirmados alimentan el dominio de Envases mediante movimientos auditables.

Romana no modifica saldos de forma opaca: la cuenta se explica con movimientos firmados y puede distinguir propiedad propia, del cliente o arrendada según el origen de la operación.

## Trazabilidad

Los eventos de Romana incluyen, entre otros:

```text
ingreso_registrado
ingreso_actualizado
ingreso_confirmado
recepcion_cerrada
```

Cada evento identifica:

- recepción;
- usuario;
- operación idempotente;
- fecha;
- transición;
- datos relevantes.

Los eventos y recepciones no se eliminan físicamente para corregir la historia.

## Aviso de Recibo

Una recepción cerrada expone un PDF con:

- número de recepción;
- horas de entrada y salida;
- temporada y cliente;
- servicio y guía;
- envases declarados;
- camión, carro y conductor;
- bruto, tara y peso neto;
- observaciones;
- espacios de firma.

El endpoint rechaza recepciones abiertas.

## Integración gerencial

`/oficina/gerencia` muestra:

- camiones en ingreso;
- camiones pendientes de destare;
- recepciones cerradas del día;
- clientes recibidos;
- envases declarados;
- peso neto diario;
- tendencia de siete días;
- alertas por camiones pendientes de salida.

## API

### Consulta

```http
GET /api/romana/catalogos
GET /api/romana/recepciones
GET /api/romana/recepciones/{id}
GET /api/romana/recepciones/{id}/aviso-recibo
```

### Operación

```http
POST /api/romana/recepciones
PUT /api/romana/recepciones/{id}
POST /api/romana/recepciones/{id}/confirmar-ingreso
POST /api/romana/recepciones/{id}/cerrar
```

Todas las rutas requieren `auth:sanctum` y el Gate correspondiente.

## Roles

| Rol | Consulta | Operación |
|---|---:|---:|
| `administrador` | Sí | Sí |
| `supervisor_frio` | Sí | Sí |
| `operador_romana` | Sí | Sí |
| `despachador` | Sí | No |
| `consulta` | Sí | No |

## Usuario local

En `local` y `testing`:

- usuario: `romana@estiba.local`;
- contraseña: `password`.

## Pendientes

- asociación explícita con lotes definitivos;
- telemetría directa desde la báscula;
- firma digital o integración documental externa;
- integración ERP;
- operación offline completa.
