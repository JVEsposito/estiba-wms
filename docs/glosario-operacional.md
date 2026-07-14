# Glosario operacional

Este documento establece el significado de los términos utilizados por Estiba WMS. Las reglas técnicas y las pantallas deben respetar estas definiciones.

## Conceptos principales

| Concepto | Definición |
|---|---|
| Folio | Número único que identifica individualmente un bulto. Es la clave operacional utilizada al escanear, buscar, ubicar y mover el bulto. |
| Bulto | Unidad física identificada por un folio. Puede ser un pallet o un saldo. |
| Pallet | Bulto completo. |
| Saldo | Bulto incompleto. Para el MVP se ubica y mueve como cualquier otro bulto; su repaletizaje queda fuera del alcance. |
| Cámara | Espacio físico configurable que contiene un conjunto de posiciones y un plano de estiba vigente. |
| Posición | Espacio físico de una cámara que puede ser ocupado por un único bulto identificado mediante folio. |
| Estiba | Distribución espacial de los bultos en las posiciones de una cámara. No es un agrupador de pallets. |
| Plano de estiba | Representación visual del estado actual de las posiciones de una cámara. |
| Sesión de estiba | Periodo durante el cual un usuario obtiene autorización exclusiva para modificar el plano de una cámara. |
| Ubicación actual | Relación vigente entre un folio y una posición. |
| Ubicación inicial o ingreso a cámara | Primera asignación de un bulto a una posición de cámara. Ambos nombres representan la misma operación. |
| Reubicación | Cambio de un bulto entre dos posiciones de la misma cámara. |
| Traslado entre cámaras | Cambio de un bulto desde una posición de una cámara hacia una posición de otra cámara. Libera el origen y ocupa el destino en una sola operación. |
| Movimiento | Registro inalterable de una ubicación inicial, reubicación, traslado entre cámaras, retiro o reversión. |
| Carga | Agrupación lógica de entre 1 y 26 bultos seleccionados para una futura salida. Se implementará después de estabilizar el núcleo de estibas. |
| Despacho | Acto de retirar uno o más folios de una cámara y enviarlos al andén. La carga queda despachada cuando todos sus folios fueron enviados. |
| Banda | Línea vertical numerada dentro de una cámara. |
| Posición | Lugar numerado dentro de una banda; `P01` corresponde al fondo y la numeración avanza hacia la entrada. |
| Andén | Destino físico al que se envían los folios durante el despacho. |
| Condición SAG | Condición operacional o regulatoria asociada al folio. Sus valores serán administrados mediante un catálogo configurable. |
| Dispositivo | Tablet autorizada para consultar o modificar la WMS. |
| Operación offline | Acción creada en una tablet sin conexión y enviada posteriormente al servidor. |
| Conflicto | Operación que no puede aplicarse automáticamente porque el estado central cambió o incumple una regla de integridad. |
| ERP | Sistema empresarial que podrá aportar datos descriptivos de los folios mediante una integración futura. La WMS no dependerá del ERP para operar. |

## Principios del dominio

- El folio identifica al bulto durante su ciclo operacional.
- Pallet y saldo son tipos de bulto, no entidades independientes.
- Una posición puede contener un solo folio.
- Un folio puede tener una sola ubicación actual.
- La estiba es un plano vivo y puede modificarse continuamente mediante nuevas sesiones.
- Varios usuarios pueden consultar una cámara, pero solo uno puede editarla.
- Cada cambio de ubicación debe producir un movimiento auditable.
- Un traslado entre cámaras debe actualizar los dos planos sin dejar estados parciales.
- Los movimientos históricos no se eliminan ni se reemplazan.
- Las cargas y despachos no forman parte del primer núcleo funcional.
- El repaletizaje podrá desarrollarse como otro módulo o proyecto.
