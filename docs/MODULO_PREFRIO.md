# Módulo de Prefrío

## Objetivo

Administrar el tratamiento térmico de pallets y saldos de producto entre Validación y su habilitación para almacenamiento, conservando procesos históricos, eventos auditables y resultados por folio.

```text
Validación
→ pendiente_prefrio
→ carga en túnel
→ proceso térmico
→ verificación
→ habilitación para almacenamiento
→ ubicación en cámara
→ disponible para carga
```

La aprobación térmica no ubica el folio ni lo deja inmediatamente disponible para una carga. Solo lo habilita para que Cámaras pueda recibirlo.

## Túneles y cámaras son dominios diferentes

Una cámara representa almacenamiento físico persistente. Un túnel representa un activo que ejecuta procesos térmicos temporales.

Por esta razón Prefrío utiliza tablas independientes:

- `tuneles_prefrio`;
- `posiciones_tunel_prefrio`;
- `procesos_prefrio`;
- `procesos_prefrio_folios`;
- `eventos_prefrio`;
- `historial_habilitaciones_almacenamiento`.

Los túneles no se incorporan como un tipo adicional de cámara.

## Configuración de túneles

Cada túnel registra:

- código correlativo `TUN-XX`;
- nombre;
- cantidad configurable de posiciones;
- setpoint habitual;
- estado administrativo;
- estado técnico;
- código externo opcional;
- observaciones;
- versión de configuración.

La capacidad puede representar túneles de 20, 22, 40 u otra cantidad par de posiciones dentro de los límites configurados. Cada profundidad contiene dos lados y el plano siempre se recorre desde el fondo hacia la entrada.

### Estado administrativo

```text
activo
inactivo
```

### Estado técnico

```text
operativo
fuera_de_servicio
mantenimiento
```

Un túnel solo puede iniciar procesos cuando se encuentra activo y operativo. No puede redimensionarse ni modificarse mientras mantiene un proceso activo.

## Procesos

Cada ciclo térmico se registra como un proceso independiente con código:

```text
PF-AAAA-NNNNNN
```

Estados:

```text
borrador
cargando
listo_para_iniciar
en_proceso
pendiente_verificacion
aprobado
requiere_reproceso
cancelado
```

Un túnel admite como máximo un proceso activo. Un folio tampoco puede participar simultáneamente en dos procesos activos.

La relación histórica permite que el mismo folio participe posteriormente en otro proceso cuando requiere reproceso.

## Folios y posiciones

Durante la carga, cada folio ocupa una posición única del proceso:

```text
TUN-01-P01
TUN-01-P02
...
```

La numeración se interpreta por pares:

```text
P01 / P02 = profundidad 1, al fondo
P03 / P04 = profundidad 2
...
último par = junto a la entrada
```

El proceso impide:

- repetir un folio;
- ocupar dos veces una posición;
- usar una posición de otro túnel;
- superar la capacidad configurada;
- cargar materiales;
- cargar folios ya habilitados para almacenamiento;
- cargar folios que permanecen ubicados en cámara.

## Condición térmica

La condición térmica se separa del estado comercial y de la ubicación física:

```text
pendiente_prefrio
en_proceso
prefrio_aprobado
requiere_reproceso
condicion_heredada
retenido
```

## Habilitación para almacenamiento

```text
no_habilitado
habilitado
retenido
```

Cámaras evalúa esta habilitación, no la existencia obligatoria de un proceso de Prefrío.

Prefrío es una fuente normal de habilitación, pero no la única. También pueden existir fuentes como:

- condición heredada desde repaletizaje;
- devolución operacional;
- contingencia autorizada;
- regularización manual auditada.

Esto permite que un pallet completo generado posteriormente desde saldos ingrese a cámara conservando una condición térmica heredada, sin fingir un nuevo proceso térmico.

La columna actual del folio permite decidir rápidamente si puede ingresar a cámara. Cada cambio se conserva además como un registro inmutable en `historial_habilitaciones_almacenamiento`, con estado resultante, condición térmica, fuente, proceso de origen, referencia, usuario, dispositivo, fecha, motivo y observación.

Al instalar esta fundación sobre inventario existente, solo los folios activos y disponibles se regularizan como habilitados. Los bloqueados quedan retenidos y los anulados, retirados, despachados o inactivos no reciben una habilitación automática.

## Resultado aprobado

Al aprobar el proceso:

```text
condicion_termica = prefrio_aprobado
habilitacion_almacenamiento = habilitado
fuente_habilitacion = prefrio_aprobado
```

El folio conserva su estado operacional anterior hasta ser ubicado. Al crearse su ubicación en una cámara de producto, el estado operacional pasa a `disponible`.

## Reproceso y retención

Al requerir reproceso:

```text
condicion_termica = requiere_reproceso
habilitacion_almacenamiento = retenido
estado_operacional = bloqueado
```

El folio conserva la relación con el proceso fallido y puede incorporarse a un proceso posterior.

Si un proceso iniciado se cancela, sus folios quedan retenidos. Si se cancela antes del inicio, se conserva la captura histórica sin afirmar que existió tratamiento térmico.

## Eventos auditables

Los eventos admitidos son:

```text
carga_iniciada
pallet_agregado
pallet_retirado
armado_confirmado
proceso_iniciado
inversion_registrada
pausa
reanudacion
deshielo
lectura
verificacion_final
aprobacion
reproceso
cancelacion
```

Cada evento conserva:

- UUID de operación;
- hash del payload;
- proceso;
- folio cuando corresponde;
- usuario;
- dispositivo;
- fecha informada por la operación;
- datos adicionales;
- observación.

Repetir el mismo UUID con el mismo contenido devuelve el estado confirmado. Reutilizarlo con contenido diferente genera conflicto.

Cada proceso utiliza además una versión incremental. Una acción basada en una versión desactualizada recibe un conflicto operacional.

## Roles

### Administrador

Puede:

- crear y modificar túneles;
- consultar y operar procesos;
- aprobar, reprocesar o cancelar;
- revisar toda la trazabilidad.

### Supervisor de frío

Puede:

- consultar y operar procesos;
- aprobar el resultado final;
- enviar a reproceso;
- cancelar justificadamente.

No administra la estructura de los túneles.

### Operador de Prefrío

Puede:

- consultar túneles y procesos;
- crear procesos;
- cargar y retirar folios antes del inicio;
- confirmar armado;
- iniciar;
- registrar inversiones, pausas, reanudaciones, deshielos y lecturas;
- enviar a verificación.

No puede crear túneles, tomar decisiones terminales, operar cámaras, cargas, materiales ni Validación.

### Consulta

Puede revisar túneles, procesos e historial sin ejecutar acciones.

## API

### Consulta

```http
GET /api/prefrio/tuneles
GET /api/prefrio/tuneles/{id}
GET /api/prefrio/procesos
GET /api/prefrio/procesos/{id}
```

### Operación

```http
POST /api/prefrio/procesos
POST /api/prefrio/procesos/{id}/folios
POST /api/prefrio/procesos/{id}/folios/{asignacion}/retirar
POST /api/prefrio/procesos/{id}/confirmar-armado
POST /api/prefrio/procesos/{id}/iniciar
POST /api/prefrio/procesos/{id}/eventos/{tipo}
POST /api/prefrio/procesos/{id}/verificar
```

### Supervisión

```http
POST /api/prefrio/procesos/{id}/aprobar
POST /api/prefrio/procesos/{id}/reprocesar
POST /api/prefrio/procesos/{id}/cancelar
```

### Administración de túneles

```http
GET  /api/administracion/prefrio/tuneles/siguiente-codigo
POST /api/administracion/prefrio/tuneles
PUT  /api/administracion/prefrio/tuneles/{id}
```

## Alcance de esta entrega

Esta fundación incorpora reglas de dominio, esquema, permisos, API, contratos públicos y pruebas.

Quedan para entregas posteriores:

- interfaz `/oficina/prefrio`;
- configuración gráfica de túneles;
- tablero de supervisión;
- pantalla móvil del operador;
- bandeja offline específica de Prefrío;
- fotografías;
- telemetría automática;
- integración con el ERP.
