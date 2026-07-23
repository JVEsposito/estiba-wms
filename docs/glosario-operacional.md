# Glosario operacional

Este documento define los términos utilizados por Estiba WMS. Las reglas, contratos API y pantallas deben respetar estas definiciones.

## Conceptos transversales

| Concepto | Definición |
|---|---|
| Temporada | Ciclo operacional global compartido por los módulos. Se crea, activa y migra desde Accesos. |
| Cliente global | Maestro transversal de una empresa o cliente de servicio. Validación y Materiales pueden configurar relaciones estacionales sobre el mismo cliente. |
| Snapshot | Copia de datos relevantes guardada dentro de un documento o proceso para que cambios posteriores del maestro no alteren la historia. |
| Dispositivo | Tablet o equipo autorizado que identifica desde dónde se ejecutó una operación. |
| Operación idempotente | Comando identificado por UUID que puede reenviarse sin ejecutar dos veces el mismo efecto. |
| Conflicto | Operación que no puede aplicarse porque el estado central cambió, el UUID fue reutilizado con otro contenido o se incumple una regla de integridad. |
| ERP | Sistema empresarial que podrá aportar maestros o datos descriptivos mediante una integración futura. La WMS no depende del ERP para operar. |

## Recepción y materia prima

| Concepto | Definición |
|---|---|
| Romana | Módulo contractual de pesaje y recepción de camiones. |
| Recepción `REC-*` | Expediente de Romana con cliente, temporada, guía, transporte, envases y pesos. No es un folio ni un lote. |
| Peso bruto | Peso del camión cargado al ingresar. |
| Tara | Peso del camión vacío al retorno. |
| Peso neto | Diferencia entre peso bruto y tara. |
| Aviso de Recibo | PDF operacional emitido para una recepción cerrada. |
| Validación MP | Revisión de materia prima posterior a Romana. Confirma envases reales y crea segregaciones provisionales. |
| Segregación | División de una recepción por CSG, cuartel, variedad u otra combinación permitida. |
| Segmento `pendiente_lote` | Resultado provisional de Validación MP que todavía no posee número de lote definitivo. |
| Lote | Identidad futura de una unidad de materia prima procesable. No se confunde con `REC-*` ni con un folio frigorífico. |

## Producto terminado y Frigorífico

| Concepto | Definición |
|---|---|
| Validación PT | Validación de pallets o saldos de producto terminado antes de Prefrío. Una aprobación crea el folio. |
| Folio | Número único que identifica individualmente un bulto de producto o material dentro del inventario. |
| Bulto | Unidad física identificada por un folio. |
| Pallet | Bulto completo de producto. |
| Saldo | Bulto incompleto de producto. Se ubica y mueve como folio; su repaletizaje formal continúa pendiente. |
| Condición SAG | Condición operacional o regulatoria asociada a un folio de producto. |
| Condición térmica | Estado del folio respecto de su tratamiento térmico: pendiente, en proceso, aprobado, reproceso, heredado o retenido. |
| Habilitación para almacenamiento | Decisión separada que determina si el producto puede ingresar a cámara. |
| Prefrío | Dominio que administra túneles y procesos térmicos antes del almacenamiento. |
| Túnel | Activo temporal de proceso térmico. No es una cámara. |
| Proceso `PF-*` | Ciclo térmico histórico ejecutado en un túnel. |
| Cámara | Espacio configurable de almacenamiento con posiciones y plano vigente. |
| Posición | Coordenada física de una cámara. En producto admite un folio; en Materiales puede admitir varias líneas del mismo cliente. |
| Banda | Línea vertical numerada dentro de una cámara. |
| Nivel | Altura o nivel físico de una posición. |
| Estiba | Distribución espacial vigente de los bultos dentro de una cámara. No es un agrupador de pallets. |
| Plano de estiba | Representación visual de las posiciones, su estado y sus ocupantes. |
| Sesión de estiba | Autorización temporal y exclusiva para modificar el plano de una cámara. |
| Ubicación actual | Relación vigente entre un folio y una posición. Se almacena separada del historial. |
| Ubicación inicial o ingreso a cámara | Primera asignación de un folio a una posición. Ambos términos representan la misma operación física. |
| Reubicación | Cambio entre posiciones diferentes de una misma cámara. |
| Traslado entre cámaras | Movimiento desde una cámara a otra que libera origen y ocupa destino en una sola transacción. |
| Retiro | Liberación de la ubicación física, conservando un movimiento histórico. |
| Movimiento | Evidencia inalterable de una ubicación, reubicación, traslado, retiro o reversa. |
| Carga `CAR-*` | Agrupación lógica de hasta 26 folios reservados para una salida. |
| Tarea de carga | Acción operacional necesaria para separar o mover un folio de una carga. |
| Andén | Destino físico documental y operacional previo al despacho. |
| Despacho | Salida física y documental de los folios de una carga. |

## Materiales

| Concepto | Definición |
|---|---|
| Ítem de material | Maestro estacional asociado a un cliente y unidad de medida. |
| Folio de material | Identidad del bulto o línea física de inventario de Materiales. |
| Bulto multilínea | Posición de Materiales que contiene varios folios o ítems del mismo cliente. |
| Cantidad inicial | Cantidad con la que se registró el folio de material. |
| Cantidad actual | Saldo físico vigente del folio. |
| Cantidad reservada | Parte comprometida en despachos abiertos. |
| Cantidad disponible | Cantidad actual menos reservas vigentes. |
| FIFO sugerido | Recomendación de retirar primero el inventario más antiguo; una excepción puede registrarse de forma auditada. |
| Retiro parcial | Salida de una cantidad que mantiene el folio ubicado mientras conserve saldo. |
| Kardex | Historial de ingresos, reservas, retiros, correcciones y otros movimientos de inventario. |
| Despacho `MAT-DES-*` | Orden de salida de Materiales con destino y centro de costo. |
| Proveedor | Maestro de origen comercial de Materiales, asociable a uno o más clientes. |

## Envases

| Concepto | Definición |
|---|---|
| Envase | Unidad logística reutilizable o consumible controlada en la cuenta corriente, como bin, tote o esponja. |
| Cuenta corriente de envases | Saldo por cliente y propiedad explicado mediante movimientos firmados. |
| Propiedad propia | Envase perteneciente a la empresa operadora. |
| Propiedad del cliente | Envase perteneciente al cliente receptor o remitente. |
| Propiedad arrendada | Envase perteneciente a un tercero o arrendador cuya cuenta debe conservarse. |
| Movimiento compensatorio | Asiento inverso que corrige o anula un movimiento sin eliminar la evidencia original. |
| Guía `GDE-*` | Documento interno para la salida de envases. No es un DTE legal. |

## Gestión técnica

| Concepto | Definición |
|---|---|
| Bandeja offline | Cola local persistente de comandos pendientes, en error o en conflicto. |
| Versión conocida | Número de versión utilizado para comprobar que el estado no cambió desde la lectura del operador. |
| Catálogo persistente | Copia local del catálogo requerido para continuar capturando con conectividad intermitente. |
| Panel gerencial | Oficina de solo lectura que consolida indicadores de Cámaras, Prefrío, Materiales y Romana. |
| Telescope | Herramienta de diagnóstico local de Laravel, restringida al servidor y no utilizada como auditoría operacional. |

## Principios del dominio

- Una identidad de un dominio no se reutiliza como identidad de otro.
- `REC-*`, lote, folio, `PF-*`, `CAR-*`, `MAT-DES-*` y `GDE-*` representan objetos distintos.
- Compartir temporada y cliente no fusiona las máquinas de estado.
- Un folio mantiene una única ubicación actual.
- Una posición de producto mantiene un único folio.
- Una posición de Materiales puede compartir ocupación únicamente entre líneas del mismo cliente.
- Cada cambio físico genera evidencia auditable.
- Las anulaciones y correcciones se explican mediante nuevos registros o movimientos compensatorios.
- Un conflicto nunca sobrescribe silenciosamente el estado central.
- El repaletizaje requiere un módulo propio y genealogía explícita de folios.
