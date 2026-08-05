# Importación de productos en recepciones de materiales

La oficina **Materiales → Recepciones → Nueva recepción** permite cargar los productos de una guía desde una planilla CSV o XLSX.

## Flujo

1. Seleccionar el cliente y el proveedor de la recepción.
2. Usar **Importar Excel** en la sección Productos recibidos.
3. Descargar la plantilla o seleccionar una planilla existente.
4. Previsualizar las filas, errores y folios estimados.
5. Cargar los productos al formulario.
6. Revisar la cabecera y los productos antes de guardar el borrador o confirmar la recepción.

La previsualización no crea recepciones, inventario ni folios. Los registros se crean únicamente mediante el flujo normal de guardado y confirmación de la recepción.

## Columnas

| Columna | Obligatoria | Descripción |
| --- | --- | --- |
| `codigo_item` | Sí | Código de un ítem activo del cliente seleccionado. |
| `cantidad_documental` | No | Cantidad indicada en la guía. Si se omite, utiliza la cantidad contada. |
| `cantidad_contada` | No | Cantidad física contada. Si se omite, se calcula como aceptada más rechazada. |
| `cantidad_aceptada` | Sí | Cantidad que ingresará al inventario. Admite cero cuando todo fue rechazado. |
| `cantidad_rechazada` | No | Cantidad rechazada; por defecto es cero. |
| `unidades_por_bulto` | Sí cuando la aceptada es mayor que cero | Cantidad máxima por folio o bulto. El último bulto recibe el diferencial. |
| `lote_proveedor` | No | Lote informado por el proveedor. |
| `fecha_fabricacion` | No | Formatos admitidos: `AAAA-MM-DD`, `DD-MM-AAAA` o `DD/MM/AAAA`. |
| `fecha_vencimiento` | No | No puede ser anterior a la fecha de fabricación. |
| `bloqueado` | No | `sí/no`, `verdadero/falso` o `1/0`. Por defecto queda sin bloqueo. |
| `motivo_bloqueo` | Sí cuando se bloquea | Motivo operacional del bloqueo. |
| `observacion` | No | Observación del producto recibido. |

También se reconocen alias operacionales como `sku`, `cantidad_guia`, `cantidad_fisica`, `cantidad_recibida`, `cantidad_por_bulto`, `lote`, `fabricacion` y `vencimiento`.

## Reglas de seguridad

- máximo 100 productos por recepción;
- máximo 500 bultos por producto;
- no se aplican filas parciales cuando existe al menos un error;
- el ítem debe pertenecer al cliente y a la temporada activa;
- el proveedor debe estar autorizado para el cliente y la categoría comercial del ítem;
- la cantidad contada debe coincidir con aceptada más rechazada;
- la suma de los bultos debe coincidir exactamente con la cantidad aceptada;
- un bulto bloqueado exige motivo.
