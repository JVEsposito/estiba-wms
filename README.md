Estiba WMS - Sistema de Gestión de Cámaras de Frío
Visión General del Proyecto
Estiba WMS es un sistema de gestión de almacenes (Micro-WMS) diseñado específicamente para resolver la trazabilidad y ubicación espacial de pallets en cámaras frigoríficas frutícolas.

El proyecto nace de la necesidad operativa real de reemplazar los planos de estiba en papel, eliminando los tiempos muertos en la búsqueda de lotes y mitigando los errores humanos. Su diseño contempla las condiciones físicas de las cámaras de frío (que actúan como jaulas de Faraday), por lo que la arquitectura final apunta a un modelo Offline-First.

Stack Tecnológico
Entorno de Desarrollo Local: Laragon, Node.js.

Backend & API REST: PHP con Laravel.

Base de Datos Central: MySQL.

Frontend Móvil (Proyección): React Native (compilación a APK nativo con SQLite/WatermelonDB para sincronización offline).

Control de Versiones: Git & GitHub.

Decisiones de Arquitectura Clave
Uso de UUIDs: Se descartaron los IDs numéricos auto-incrementables en favor de UUIDs para todas las tablas primarias. Esto es vital para la futura operación offline en la tablet, evitando colisiones de IDs cuando múltiples usuarios sincronicen datos simultáneamente al recuperar conexión WiFi en los pasillos.

Sistema de Cuadrícula Visual (Coordenadas): Las posiciones físicas no se manejan con múltiples tablas (bandas, pasillos), sino como un sistema de coordenadas X, Y, Z (fila, profundidad, altura) dentro de una sola tabla, optimizando las consultas para renderizar el mapa 2D en el dispositivo móvil.

Gestión de Saldos (Repas): Los pallets incompletos no se separan en tablas anexas para mantener la integridad del mapa de estiba. Se identifican mediante un flag booleano (es_saldo) y, al ser consolidados, cambian a un estado histórico (consolidado_repa) liberando su posición espacial.

Esquema de Base de Datos (Migraciones Actuales)
El modelo relacional inicial consta de 6 entidades principales:

camaras: Contenedores físicos.

Define el nombre, tipo (pre_frio, almacenaje, despacho) y capacidad_maxima.

registros_temperatura: Historial térmico.

Vinculada a camaras. Registra grados, observacion y el timestamp de la medición para control de calidad.

despachos: Cabecera de salida.

Agrupa lotes bajo una guia_despacho, identificando exportadora, patente_camion y destino.

folios: Entidad central (Pallets/Carga).

Contiene la metadata del código de barras (numero_folio, variedad, calibre, marca, exportadora).

Depende opcionalmente de despachos_id.

Controla el flujo mediante estado ('en_recepcion', 'en_camara', 'despachado', 'consolidado_repa') y el flag es_saldo.

posicions: El mapa físico (Asientos).

Vincula una camara_id con un folio_id (si está ocupado).

Almacena las coordenadas exactas de la estiba.

movimientos: Bitácora de trazabilidad.

Registra cada interacción (ingreso, reubicacion, despacho), vinculando el pallet con su posición de origen y destino, permitiendo auditar la cadena de movimientos cronológicamente.

Estado Actual
[x] Diseño del Modelo Entidad-Relación.

[x] Configuración del entorno local.

[x] Ejecución exitosa de Migraciones en MySQL.

[ ] (Siguiente paso) Configuración de Modelos Eloquent y sus relaciones.

[ ] (Siguiente paso) Creación de Seeders para inyección de volumen de prueba.