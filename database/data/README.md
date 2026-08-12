# Catálogo de países para Inspección SAG

`paises_iso_3166_1.json` se generó desde `i18n-iso-countries` 7.14.0 (MIT),
utilizando su tabla de códigos y traducciones al español. Incluye el catálogo
ISO 3166-1 y la entrada operacional XK/Kosovo que la fuente mantiene para
evitar excluir un destino utilizado internacionalmente.

La aplicación copia estos datos a su catálogo global durante la migración. Los
lotes SAG conservan snapshots de los nombres y de la composición de bloques,
por lo que una actualización futura no altera inspecciones históricas.

## Puntos de transporte para embarques

`puertos_embarque.json` carga el catálogo inicial de puertos, aeropuertos y
pasos utilizados por la planificación de embarques. Cada registro se vincula
al catálogo global de países mediante su código ISO alpha-2. La operación usa
las claves foráneas para evitar combinaciones inválidas y conserva el nombre
como snapshot en cada embarque o instructivo para no alterar documentos
históricos si el catálogo cambia posteriormente.
