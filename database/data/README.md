# Catálogo de países para Inspección SAG

`paises_iso_3166_1.json` se generó desde `i18n-iso-countries` 7.14.0 (MIT),
utilizando su tabla de códigos y traducciones al español. Incluye el catálogo
ISO 3166-1 y la entrada operacional XK/Kosovo que la fuente mantiene para
evitar excluir un destino utilizado internacionalmente.

La aplicación copia estos datos a su catálogo global durante la migración. Los
lotes SAG conservan snapshots de los nombres y de la composición de bloques,
por lo que una actualización futura no altera inspecciones históricas.
