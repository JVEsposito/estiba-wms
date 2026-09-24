# Archivo de temporadas

La información de una temporada es historial trazable: se conserva completa durante la
temporada y **al menos 60 días después de su cierre** (`fecha_fin`). Recién entonces el
administrador puede generar su archivo. Generarlo **no borra ningún dato**; la depuración de
la base es un paso posterior (fase C2) que solo será posible con un archivo verificado.

## Quién y cuándo

- Solo un usuario activo con rol `administrador`.
- La temporada no puede estar activa y debe tener `fecha_fin` con al menos
  `ARCHIVO_TEMPORADAS_DIAS_CIERRE` días (60 por defecto).
- Oficina **Accesos y temporadas → Temporadas → Seleccionar acción → Archivo**. El proceso corre
  en la cola; la ventana muestra el avance y permite descargar el paquete verificado.
- Para temporadas muy grandes, o si la cola no alcanza a terminar en su tiempo límite:

  ```bash
  php artisan temporadas:archivar 2025-2026 --administrador=correo@planta.cl
  ```

## Contenido del paquete (ZIP)

| Archivo | Contenido |
|---|---|
| `manifiesto.json` | Temporada, fecha, responsable, versión de la base, y por tabla: filas propias, filas referenciadas, total y SHA-256. |
| `esquema.sql` | `CREATE TABLE` de todas las tablas incluidas. |
| `datos/<tabla>.sql` | `INSERT` de las filas incluidas, una fila por línea. |
| `excel/<tabla>.xlsx` | Líneas limpias (formato base de datos) de folios, validaciones, trazabilidad, cargas, asignaciones, movimientos, recepciones, lotes MP y entregas a proceso. |

### Qué filas se incluyen

El archivo se deriva del grafo de llaves foráneas de la base, no de una lista mantenida a mano:

1. **Propias**: filas con una llave foránea a esa temporada y, por cierre, sus filas hijas
   (una carga de la temporada, sus folios asignados, las incidencias de esos folios, etc.).
2. **Referenciadas**: filas de otras tablas que las anteriores usan (usuarios, cámaras,
   posiciones, clientes, catálogos). Solo las usadas, para que el archivo se restaure por sí
   mismo sin arrastrar datos de otras temporadas.

Se excluyen las tablas técnicas (`jobs`, `sessions`, `cache`, tokens, Telescope…); la lista
está en `config/archivo_temporadas.php`.

## Verificación

Después de generarlo, el sistema vuelve a leer el paquete desde su disco y comprueba el SHA-256
del ZIP, el de cada archivo interno y la cantidad de filas de cada tabla contra el manifiesto.
Solo entonces queda **Verificado** y disponible para descargar. Cualquier diferencia lo deja
**Falló** con el motivo.

## Restauración

En una base vacía (por ejemplo, para consultar una temporada ya depurada):

```bash
mysql -u root -p -e "CREATE DATABASE estiba_historico_2025 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
unzip 2025-2026_*.zip -d archivo
mysql -u root -p estiba_historico_2025 < archivo/esquema.sql
for f in archivo/datos/*.sql; do mysql -u root -p estiba_historico_2025 < "$f"; done
```

Probado con una temporada de 150.000 folios y 300.000 líneas de trazabilidad: el archivo
tardó 2 min 55 s, pesó 40,6 MB y se restauró en 35 s con los mismos conteos.

## Almacenamiento

Los paquetes se guardan en el disco `archivo_temporadas` (`config/filesystems.php`):

- Por defecto `storage/app/archivo-temporadas`; `ARCHIVO_TEMPORADAS_RUTA` lo cambia a otra
  carpeta del servidor de planta (por ejemplo, un NAS montado).
- `ARCHIVO_TEMPORADAS_DISK` permite usar un disco en la nube (s3) cuando corresponda.
- Conviene copiar cada paquete verificado a un segundo medio: el SHA-256 del manifiesto permite
  comprobar esa copia.
