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

La cantidad inicial y la cantidad total empresa son atributos globales del folio. Las cantidades actual, reservada y disponible son contextuales al almacén indicado en la fila.

## Navegación

La oficina se encuentra en el macromódulo Materiales, pestaña **Custodia**, y utiliza la ruta `/oficina/materiales/almacenes`.
