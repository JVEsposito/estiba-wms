# Módulo de cámaras de materiales

## Objetivo

Controlar materiales almacenados en cámaras o bodegas usando el mismo plano,
sesiones exclusivas y motor de movimientos de Estiba WMS, pero permitiendo
retiros parciales por cantidad.

## Modelo operacional

- Una cámara se clasifica como `productos` o `materiales` al configurarla.
- Una cámara ocupada no puede cambiar de clasificación.
- Un folio de material representa un único ítem y ocupa una posición física.
- El ítem se selecciona desde el catálogo administrado en oficina.
- El ingreso registra cantidad inicial, unidad de medida, lote, proveedor y
  observación. La unidad se copia desde el catálogo y no puede cambiarse en un
  ítem que ya tenga folios.
- El saldo actual, el saldo reservado y el disponible se conservan separados.
- Los folios de material solo pueden moverse entre cámaras de materiales.

## Catálogos de oficina

El administrador mantiene:

1. Ítems: código, nombre, categoría, unidad de medida y referencia externa
   opcional.
2. Destinos: nombre, centro de costo, descripción y referencia externa
   opcional.

Los registros se activan o desactivan; no se eliminan físicamente. Los campos
de integración permiten que una futura sincronización con ERP mantenga la
identidad interna del registro.

## Despacho por cantidades

Un despacho puede crearse desde `/oficina/materiales` o desde una tablet. Cada
línea solicita una cantidad de un ítem. El sistema reserva folios por fecha de
ingreso y número de folio, y devuelve esas reservas como sugerencia FIFO.

FIFO no bloquea la operación: el camarero puede retirar desde otro folio. La
decisión queda registrada en `retiros_materiales.siguio_fifo`.

Cada retiro:

- exige una sesión activa y el bloqueo de la cámara correspondiente;
- registra usuario, tablet, posición, destino y centro de costo;
- descuenta únicamente la cantidad confirmada;
- conserva el folio en su posición mientras tenga saldo;
- libera la posición y cierra el folio cuando su saldo llega a cero;
- actualiza el kardex dentro de la misma transacción MySQL.

La creación de despachos y los retiros reciben un UUID de operación. Repetir
la misma solicitud devuelve el resultado ya confirmado y no duplica reservas
ni descuentos.

## Pantallas

- `/oficina/camaras`: crea y administra cámaras de productos o materiales.
- `/oficina/materiales`: mantiene catálogos, crea órdenes, consulta existencia
  y revisa despachos.
- `/`: operación web de cámara, incluyendo ingreso y retiro de materiales.
- `mobile/`: cliente Expo/React Native con las mismas operaciones para la APK.

## Fuera de esta entrega

- sincronización efectiva con ERP;
- ajustes de inventario y devoluciones desde interfaz;
- cola offline persistente;
- equivalencias o conversiones entre unidades de medida.
