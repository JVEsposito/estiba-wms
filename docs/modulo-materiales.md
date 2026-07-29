# Módulo de cámaras de materiales

## Objetivo

Controlar materiales almacenados en cámaras o bodegas usando el mismo plano,
sesiones exclusivas y motor de movimientos de Estiba WMS, pero permitiendo
retiros parciales por cantidad.

## Modelo operacional

- Una cámara se clasifica como `productos` o `materiales` al configurarla.
- Una cámara ocupada no puede cambiar de clasificación.
- Un folio de material representa un único ítem. Una posición puede contener
  varios folios pequeños cuando la operación física los mantiene apilados en
  el mismo bulto o sector autorizado.
- El ítem se selecciona desde el catálogo administrado en oficina.
- El ingreso registra cantidad inicial, unidad de medida, lote, proveedor y
  observación. La unidad se copia desde el catálogo y no puede cambiarse en un
  ítem que ya tenga folios.
- El saldo actual, el saldo reservado y el disponible se conservan separados.
- Los folios de material solo pueden moverse entre cámaras de materiales.

## Catálogos de oficina

Los clientes son transversales y se crean, modifican o desactivan únicamente
en `/oficina/accesos`. Cada temporada conserva una proyección técnica del
cliente global para asociar ítems e inventario sin duplicar el maestro.

En Materiales el administrador mantiene:

1. Ítems: código, nombre, categoría, unidad de medida y referencia externa
   opcional.
2. Proveedores: código, nombre, referencia externa y uno o más clientes
   globales asociados.
3. Destinos: nombre, centro de costo, descripción y referencia externa
   opcional.

Los registros se activan o desactivan; no se eliminan físicamente. Los campos
de integración permiten que una futura sincronización con ERP mantenga la
identidad interna del registro.

## Recepción y conciliación física

Recepción de Materiales distingue cuatro cantidades por ítem:

```text
cantidad documental = lo indicado por la guía
cantidad contada = lo observado físicamente
cantidad aceptada = suma de los bultos que generan folio
cantidad rechazada = parte contada que no ingresa al inventario
```

La conciliación obligatoria es:

```text
cantidad contada = cantidad aceptada + cantidad rechazada
```

La cantidad documental puede diferir de la contada y esa diferencia permanece
visible como antecedente operacional. Los folios se generan exclusivamente por
los bultos aceptados. Un ítem totalmente rechazado conserva su línea documental
y física, pero no crea folios ni saldo de inventario.

El formato del folio es único: `F` + las dos letras configuradas para el cliente
+ un correlativo de siete dígitos. Por ejemplo, el primer folio del cliente
`GE` es `FGE0000001`; las letras centrales cambian según el cliente y no forman
un prefijo fijo compartido.

El contrato mantiene temporalmente `cantidad_recibida` como alias de
`cantidad_aceptada` para no interrumpir clientes móviles anteriores.

### Oficina de recepciones

`/oficina/materiales/recepciones` permite crear, consultar y confirmar
recepciones. La distribución física se ingresa indicando la cantidad aceptada
y las unidades por bulto; el sistema genera todos los bultos y calcula el
último con el diferencial.

La edición y eliminación administrativa son exclusivas del rol administrador.
Una recepción confirmada solo puede corregirse o eliminarse si todos sus folios
conservan el saldo original y todavía no fueron ubicados, reservados, retirados,
bloqueados, despachados ni utilizados en transformaciones u otros procesos.

La eliminación física conserva un snapshot en
`eliminaciones_recepciones_materiales`. Sus números se incorporan a
`folios_materiales_liberados` y el siguiente ingreso del mismo cliente los
consume en orden antes de avanzar el correlativo. Si existían etiquetas
impresas, sus trabajos se invalidan y las copias físicas deben destruirse antes
de reutilizar el número.

## Bloqueo supervisado

Administrador y supervisor de Materiales pueden bloquear un folio activo que no
posea reservas. El bloqueo exige motivo, retira inmediatamente el saldo de la
disponibilidad y no elimina su ubicación ni su existencia física.

La liberación también exige motivo y registra usuario, fecha, estado anterior y
estado resultante:

- un folio ubicado vuelve a `disponible`;
- un folio sin ubicación vuelve a `pendiente_ubicacion`.

Cada acción utiliza UUID idempotente y queda registrada en
`eventos_bloqueos_materiales`. Camareros, despachadores y perfiles de consulta
pueden observar el estado, pero no modificarlo.

El panel gerencial separa stock actual, disponible, reservado, bloqueado y
pendiente de ubicación. Solo se considera disponible el saldo de folios
ubicados en posiciones y cámaras de Materiales activas, con estado
`disponible` y sin motivo de bloqueo.

### Cambio de temporada e inventario

Solo el administrador ejecuta la migración desde `/oficina/accesos`. Puede
copiar ítems y sus asociaciones de cliente hacia una temporada vacía sin
copiar cantidades. Los clientes globales ya están disponibles en todo ciclo.
Si
además selecciona inventario, el destino se activa globalmente en la misma
transacción y cada folio con saldo positivo se vincula al ítem equivalente de
la nueva temporada. El número de folio, la posición, el saldo y el kardex
histórico se conservan.

La migración de inventario se rechaza si existen despachos pendientes o
parciales, reservas abiertas o ítems sin equivalencia en el destino. Cada
ejecución y cada folio trasladado quedan registrados en la auditoría de
migraciones de temporada.

### Importación masiva del catálogo

El administrador puede cargar ítems desde `/oficina/materiales` usando una
planilla CSV o XLSX. La plantilla admite las columnas `temporada_codigo`,
`cliente_codigo`, `codigo`, `nombre`, `categoria`, `unidad_medida`,
`codigo_externo` y `activo`, con un máximo de 5.000 filas de datos por archivo.
La temporada debe existir y `cliente_codigo` debe corresponder a un cliente
activo creado previamente en Accesos.

La carga se ejecuta en dos etapas:

1. previsualización de filas válidas, errores, creaciones y actualizaciones;
2. confirmación transaccional de una planilla sin errores.

La importación solo modifica `items_materiales`: no crea folios, cantidades,
reservas ni movimientos. Un ítem ausente de la planilla conserva su estado y
los campos opcionales vacíos no borran datos existentes. Tampoco se permite
cambiar la unidad de medida cuando el ítem ya tiene folios asociados. Cada
intento guarda el nombre y checksum del archivo, las filas procesadas, el
resumen, el usuario y la fecha de confirmación para auditoría. El archivo
original no se conserva. Si el catálogo cambia entre la previsualización y la
confirmación, la operación se rechaza y exige una nueva previsualización.

## Despacho por cantidades

Un despacho puede crearse desde `/oficina/materiales` por administrador,
supervisor de materiales o despachador. Un supervisor de materiales también
puede crearlo desde una tablet. El camarero de materiales ejecuta retiros sobre
órdenes existentes, pero no crea ni cancela despachos. Cada línea solicita una
cantidad de un ítem. El sistema reserva folios por fecha de ingreso y número de
folio, y devuelve esas reservas como sugerencia FIFO. El despacho queda ligado
a la temporada global activa y no admite ítems de otro ciclo.

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
- `/oficina/materiales/recepciones`: registra guías, confirma folios y permite
  la corrección o eliminación controlada por administrador.
- `/`: operación web de cámara, incluyendo ingreso y retiro de materiales.
- `mobile/`: cliente Expo/React Native con las mismas operaciones para la APK.

## Fuera de esta entrega

- sincronización efectiva con ERP;
- ajustes de inventario y devoluciones desde interfaz;
- cola offline persistente;
- equivalencias o conversiones entre unidades de medida.
