# Módulo de Transformación de Materiales

## Estado de esta entrega

Esta entrega funda el dominio de transformación sobre el inventario de Bodega ya
consolidado por Recepción de Materiales. No crea una segunda contabilidad ni
convierte una transformación interna en una recepción ficticia.

El módulo incorpora:

- recetas con versiones históricas;
- un único producto principal `material_pt` por receta;
- componentes de entrada `material_mp` o `insumo`;
- identificación explícita del componente principal;
- órdenes en borrador y planificación;
- reservas FIFO sobre folios existentes, disponibles y ubicados;
- cancelación con liberación transaccional de reservas;
- inicio operacional desde PDA;
- lotes parciales con un único lote abierto por orden;
- consumos reales contra reservas y saldo de inventario;
- control y justificación de excepciones FIFO;
- generación de folios de salida con el correlativo global del cliente;
- cálculo de merma por lote y genealogía de entradas y salidas;
- cierre de orden con liberación del saldo reservado no consumido.

Quedan para entregas posteriores:

- impresión de etiquetas de transformación desde la misma pantalla;
- reversa compensatoria de lotes cerrados;
- indicadores de transformación en el panel gerencial.

## Principio de inventario único

Los saldos continúan viviendo en:

```text
folios_materiales.cantidad_actual
folios_materiales.cantidad_reservada
movimientos_inventario_materiales
ubicaciones_actuales
```

Las tablas de transformación documentan receta, orden, lote y genealogía. No
mantienen un inventario paralelo.

## Flujo objetivo

```text
Receta versionada
→ orden de transformación
→ planificación
→ reserva FIFO de materiales
→ inicio
→ lotes parciales
→ consumos reales
→ generación de folios de salida `material_pt`
→ folios visibles y pendientes de ubicación
→ estiba en cámara de Materiales
→ disponibilidad para reservas y despacho
→ cierre con merma y desviaciones
```

## Recetas y versiones

Una receta define un ítem de salida de categoría `material_pt` y uno o más
componentes de entrada de categoría `material_mp` o `insumo`.

Cada versión conserva:

- cantidad base de salida;
- unidad de medida de salida;
- cantidades estándar por componente;
- unidad de medida de cada componente;
- componente principal;
- factor de conversión;
- merma estándar;
- tolerancia operacional;
- snapshot histórico.

Debe existir exactamente un componente principal. Una versión utilizada por una
orden no se modifica: todo cambio posterior debe crear una nueva versión.

## Órdenes

Estados previstos:

```text
borrador
→ planificada
→ en_proceso
→ pendiente_cierre
→ cerrada

borrador | planificada
→ cancelada
```

La ejecución implementa todos estos estados. La orden pasa a
`pendiente_cierre` cuando la salida acumulada alcanza la cantidad planificada;
también puede cerrarse antes, siempre que se justifique la desviación.

Cada orden conserva temporada global, cliente global, versión de receta,
cantidad planificada, línea, turno, fecha operacional, snapshot de receta y
eventos auditables. La planificación utiliza exclusivamente el snapshot de la
orden; por ello, una nueva versión puede retirar la versión anterior sin
invalidar órdenes en borrador ya creadas.

## Reservas FIFO

Al planificar una orden, el sistema calcula cada requerimiento en proporción a la
cantidad base de la versión:

```text
cantidad_requerida =
    cantidad_estandar_componente
    × cantidad_planificada_salida
    ÷ cantidad_base_salida
```

Solo pueden reservarse folios que:

- correspondan al ítem requerido;
- estén activos;
- estén ubicados en una cámara de Materiales;
- tengan estado operacional `disponible`;
- no posean motivo de bloqueo;
- tengan saldo no reservado.

La reserva utiliza FIFO por fecha de ingreso y número de folio. La cantidad se
refleja en `folios_materiales.cantidad_reservada`, por lo que también queda
indisponible para despachos concurrentes.

Si falta saldo para cualquier componente, toda la planificación se revierte.

## Cancelación

Una orden solo puede cancelarse sin compensaciones mientras permanezca en
`borrador` o `planificada`.

La cancelación de una orden planificada:

- bloquea la orden y las reservas;
- devuelve las cantidades reservadas a cada folio;
- cambia las reservas a `liberada`;
- registra usuario, operación, motivo y evento;
- es idempotente por UUID de comando cuando coinciden orden, usuario y motivo;
- rechaza como conflicto cualquier reutilización alterada del mismo UUID.

Una orden con consumos futuros no podrá cancelarse directamente: deberá
revertirse mediante movimientos compensatorios.

## Ejecución PDA, lotes y genealogía

La PDA lista las órdenes planificadas y en ejecución. El operador puede iniciar
una orden, abrir un lote parcial, pistolear los folios reservados, registrar
cantidades reales y cerrar el lote. Solo puede existir un lote abierto por
orden.

Cada lote vincula:

```text
folios de entrada
→ cantidades consumidas
→ lote de transformación
→ folios de salida con prefijo por cliente
```

El origen de los materiales se remonta a Recepción de Materiales y sus guías de
proveedor. `REC-*` pertenece a Romana y no forma parte de esta genealogía.

Los folios producidos utilizan el mismo correlativo global por cliente que
Recepción de Materiales:

```text
F + código de cliente de 2 letras + correlativo de 7 dígitos
```

Nacen como existencia visible, `pendiente_ubicacion` y no reservable. La
primera estiba los promoverá a `disponible`, salvo que estén bloqueados.

El cierre de lote es una sola transacción:

- valida que todos los folios pertenezcan a reservas activas de la orden;
- exige consumo para cada componente de la receta;
- descuenta `cantidad_actual` y `cantidad_reservada`;
- retira la ubicación y desactiva el folio cuando su saldo llega a cero;
- registra consumo, movimiento de inventario, usuario y dispositivo;
- genera el FAG de salida y su movimiento positivo;
- calcula merma y cierra el lote;
- revierte todo si falla cualquiera de estos pasos.

Si se selecciona un folio posterior mientras un folio FIFO anterior conserva
saldo, el motivo de excepción es obligatorio.

## Merma

La merma global se calculará únicamente sobre el componente principal, evitando
sumar unidades incompatibles.

```text
salida_teorica =
    consumo_real_componente_principal × factor_conversion

merma_estandar =
    salida_teorica × porcentaje_merma_estandar

salida_esperada =
    salida_teorica − merma_estandar

merma_real =
    salida_teorica − salida_real

desviacion_merma =
    merma_real − merma_estandar
```

Los componentes auxiliares tendrán desviación de consumo individual:

```text
consumo_real_componente − consumo_estandar_componente
```

Una merma real negativa se conserva como dato operacional; no se rechaza por sí
misma.

## Permisos

- administrador y supervisor de Materiales: crear recetas, crear y planificar
  órdenes, cancelar antes del consumo;
- administrador, supervisor y camarero de Materiales: iniciar órdenes, abrir y
  cerrar lotes y cerrar la orden desde PDA;
- despachador y consulta: lectura según su capacidad de consulta vigente;
- el login publica capacidades explícitas para consultar transformaciones,
  gestionarlas, operarlas y administrar recetas.
