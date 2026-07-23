# Alcance funcional actual

## Objetivo

Estiba WMS administra operaciones de recepción, validación, tratamiento térmico, almacenamiento, inventario y despacho mediante una base central auditada, oficinas web y una aplicación Android para tablets y PDA.

El sistema debe ser simple para el operador, estricto con la integridad del inventario y capaz de conservar operaciones críticas ante conectividad intermitente en los módulos que cuentan con bandeja offline.

## Alcance transversal

### Usuarios y dispositivos

- Autenticación mediante Laravel Sanctum.
- Usuarios activos con roles y capacidades explícitas.
- Registro de tablets autorizadas.
- Identificación de usuario y dispositivo en sesiones, movimientos y eventos.
- Cierre de sesión anterior al iniciar en otro equipo cuando la política lo exige.
- Separación de consulta, operación, supervisión y administración.

### Temporadas

- Temporada operacional global compartida por los módulos.
- Creación, edición, activación y migración exclusivamente desde Accesos.
- Conservación de la temporada original en procesos históricos.
- Copia controlada de catálogos estacionales.
- Migración opcional del inventario vivo de Materiales.
- Bloqueo de migración cuando existen reservas o despachos abiertos.

### Clientes y proveedores

- Maestro global de clientes administrado desde Accesos.
- Configuraciones estacionales para Validación y Materiales.
- Proveedores de Materiales con asociaciones a clientes.
- Snapshots contractuales en los documentos que deben conservar el nombre y código históricos.

## Recepción de materia prima

### Romana

Incluye:

- registro inicial del peso bruto;
- correlativo mensual `REC-AAMM-####` asignado al crear la recepción;
- antecedentes del camión, carro, conductor, cliente, temporada y guía;
- declaración de bins, totes y esponjas;
- confirmación de ingreso;
- captura de tara al retorno;
- cálculo del peso neto;
- cierre irreversible;
- eventos auditables;
- Aviso de Recibo PDF;
- indicadores diarios en el panel gerencial.

Romana no crea folios, lotes ni movimientos de Frigorífico.

### Validación MP

Incluye:

- bandeja de recepciones pendientes;
- búsqueda o pistoleo mediante el correlativo `REC-*`;
- toma exclusiva de una recepción;
- herencia de cliente, temporada, guía y transporte;
- conteo real de bins, totes y esponjas;
- diferencia informativa respecto de lo declarado;
- revisión visual de tarjas;
- recepción con fruta o solo de envases;
- segregación por CSG, cuartel y variedad;
- segmentos provisionales `pendiente_lote`;
- confirmación de cantidades reales en el kardex de envases.

No incluye todavía la creación del número de lote definitivo.

### Envases

Incluye:

- cuenta corriente y existencia por cliente;
- movimientos firmados para compra, arriendo, recepción, despacho, anulación y ajustes permitidos;
- revisión u observación de movimientos;
- guías internas correlativas `GDE-AAMM-NNNN`;
- líneas independientes para bins, totes y esponjas;
- propiedad propia, del cliente o arrendada;
- descuento físico y cuenta corriente al confirmar;
- anulación mediante movimientos compensatorios;
- conservación de la cuenta del arrendador cuando corresponde.

Las guías son documentos operacionales internos y no DTE legales.

## Frigorífico

### Validación PT

- Catálogo jerárquico estacional.
- Clientes globales con marcas estacionales.
- Especies, variedades, calibres, envases, categorías y CSG.
- Proyección compatible con la PDA.
- Importación CSV/XLSX con previsualización y confirmación.
- Captura móvil vertical.
- Aprobación, observación y rechazo.
- Intentos históricos e idempotencia.
- Creación atómica del folio aprobado como `pendiente_prefrio`.
- Caché de catálogo y bandeja offline por usuario y dispositivo.

### Prefrío

- Túneles independientes de las cámaras.
- Posiciones configurables y plano de dos lados.
- Procesos históricos con versión incremental.
- Carga y retiro de folios antes del inicio.
- Confirmación de armado, inicio y envío a verificación.
- Lecturas, inversión, pausa, reanudación y deshielo.
- Aprobación, reproceso y cancelación.
- Condición térmica separada de la ubicación y del estado comercial.
- Habilitación para almacenamiento.
- Oficina de supervisión y aplicación móvil horizontal.
- Bandeja offline e idempotencia.

### Cámaras y estiba

- Creación y edición de cámaras.
- Contenido separado entre producto y materiales.
- Bandas, posiciones, niveles y estado de cada posición.
- Consulta concurrente del plano.
- Una sesión exclusiva de edición por cámara.
- Ubicación inicial, reubicación, traslado y retiro.
- Versiones de plano y control de concurrencia.
- Historial completo de movimientos.
- Consulta previa del folio y autocompletado de datos nacidos en Validación.
- Ingreso inicial de producto aprobado en Prefrío.

En cámaras de producto una posición admite un único folio. En cámaras de materiales una posición puede contener varias líneas del mismo cliente.

### Cargas y despacho

- Cargas `CAR-*` de hasta 26 folios.
- Borrador, publicación, separación y cancelación.
- Reserva exclusiva del folio en una carga vigente.
- Distribución por cámara desde la ubicación actual.
- Tareas y plan de extracción.
- Incidencias y resolución.
- Envío individual a andén.
- Cierre documental del despacho.
- Liberación de ubicaciones mediante movimientos auditados.

## Bodega de materiales

Incluye:

- catálogo estacional por cliente;
- importación CSV/XLSX solo de maestros;
- proveedores y asociaciones con clientes;
- ingreso y ubicación de bultos;
- posiciones multilínea para materiales del mismo cliente;
- inventario inicial, actual, reservado y disponible;
- retiros parciales;
- reserva y sugerencia FIFO;
- registro de excepciones FIFO;
- destinos y centros de costo;
- despachos de materiales;
- kardex completo;
- notificaciones persistentes;
- corrección supervisada del código de un ítem con motivo y restricciones.

La importación del catálogo no crea folios, existencias ni movimientos.

## Gestión y diagnóstico

### Panel gerencial

- Indicadores de ocupación y capacidad de cámaras.
- Producto disponible.
- Inventario de Materiales separado por unidad de medida.
- Estado y ocupación de túneles de Prefrío.
- Recepciones, peso neto y tendencia de Romana.
- Alertas operacionales.
- Lectura automática y refresco manual.
- Sin acciones de escritura.

### Telescope

- Disponible solo en entorno `local`.
- Acceso restringido a loopback.
- Ocultamiento de tokens, contraseñas y cabeceras sensibles.
- Limpieza programada de entradas antiguas.

## Operación offline

Implementada específicamente en:

- Validación PT: catálogo local y bandeja de capturas.
- Prefrío: túneles, procesos, catálogo de folios y bandeja de comandos.

Las operaciones guardan el UUID antes de intentar transmitirlo. Los conflictos no sobrescriben silenciosamente el estado central.

La operación offline completa de Cámaras, Materiales, Cargas, Romana y Validación MP continúa siendo una evolución pendiente.

## Integración futura

El modelo conserva:

- sistema de origen;
- identificadores externos;
- estados de vinculación;
- datos externos y snapshots;
- fechas de sincronización;
- adaptadores previstos para API, Webservice, ODBC o archivos.

La WMS es la fuente de verdad de ubicaciones y movimientos. Una futura integración ERP podrá enriquecer datos, pero no debe modificar directamente la trazabilidad física.

## Fuera del alcance actual

- Integración automática con Suit Export u otro ERP productivo.
- Creación definitiva de lotes desde Validación MP.
- Repaletizaje y genealogía de saldos.
- Impresión de etiquetas.
- Telemetría automática desde equipos de frío o romana.
- Evidencia fotográfica en todos los procesos.
- Operación multi-planta.
- Documentos tributarios electrónicos.

## Criterios de operación controlada

El sistema se considera apto para un piloto cuando:

- los catálogos reales han sido validados por operación;
- los roles solo observan y ejecutan acciones de su ámbito;
- ningún folio ocupa dos ubicaciones incompatibles;
- las posiciones y procesos mantienen exclusividad según su dominio;
- los reintentos no duplican movimientos;
- los conflictos quedan visibles;
- los documentos y procesos conservan su temporada y snapshots históricos;
- una prueba física coincide con Cámaras, Materiales, Prefrío, Romana y Envases;
- existen respaldos y un procedimiento de contingencia durante el piloto.
