# Exportaciones de custodia distribuida

La existencia exportable de Materiales representa la custodia vigente dentro de la empresa y no únicamente lo disponible físicamente en Bodega Central.

## Unidad de registro

Cada fila corresponde a la combinación de un folio y un almacén con saldo positivo.

- Una transferencia parcial puede mostrar el mismo folio en Bodega Central y en uno o más centros de costo.
- Una transferencia total elimina la fila de Bodega Central, pero mantiene el folio bajo el almacén virtual de destino.
- Un consumo o ajuste negativo reduce la existencia del almacén y el total empresa.
- Cuando el total empresa llega a cero, el folio deja de formar parte de la existencia vigente.

## Campos de custodia

La exportación de Materiales informa:

- tipo de almacén;
- código y nombre del almacén;
- centro de costo;
- cantidad actual, reservada y disponible en ese almacén;
- cantidad total vigente en la empresa;
- cámara y posición cuando la custodia es física.

El código de almacén es el identificador interno generado por el WMS. El centro de costo conserva el código operacional o contable del área receptora, por ejemplo `PACK-01`.

La cantidad inicial y la cantidad total empresa son atributos globales del folio. Para que ambas columnas puedan sumarse sin duplicar inventario, se informan solamente en la primera fila de cada folio; las filas adicionales dejan esos campos vacíos. Las cantidades actual, reservada y disponible son contextuales al almacén indicado en la fila.

**Disponible para reserva** se informa únicamente para saldos disponibles en Bodega Central. Un saldo de almacén virtual puede estar disponible para consumo, devolución o transferencia, pero no participa en la reserva FIFO de despachos desde Bodega.

## Navegación

La oficina se encuentra en el macromódulo Materiales, pestaña **Custodia**, y utiliza la ruta `/oficina/materiales/almacenes`. La pestaña reutiliza el módulo autorizado de Inventario; el formulario de movimientos y el kardex se muestran de forma independiente según las capacidades del usuario.
