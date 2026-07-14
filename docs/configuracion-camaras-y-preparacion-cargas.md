# Configuración de cámaras y preparación de cargas

Este documento fija el vocabulario y las reglas operativas acordadas antes de
modificar la estructura de datos o agregar módulos de oficina.

## Vocabulario físico

- **Cámara:** recinto físico en el que se ubican bultos. Su código operacional
  es correlativo y estable, por ejemplo `CAM-01`.
- **Banda:** eje numérico que los camareros utilizan para identificar una línea
  vertical dentro de la cámara. Reemplaza el término anterior `fila`.
- **Posición:** lugar dentro de una banda. La posición `1` corresponde al fondo
  y es la primera que normalmente se ocupa. La numeración aumenta desde el
  fondo hacia la entrada. Reemplaza el término anterior `profundidad`.
- **Nivel:** altura física de una posición, numerada desde `1`.
- **Etiqueta de posición:** `B01-P01-N1`.
- **Ubicación completa:** combina cámara y posición, por ejemplo
  `CAM-01/B01-P01-N1`.

Los identificadores UUID continúan siendo las claves internas. Los códigos y
etiquetas operacionales son estables una vez que existe trazabilidad asociada.

## Representación del plano

- Cada banda se representa verticalmente.
- El fondo se muestra arriba y la entrada abajo.
- Las posiciones se ordenan desde `P01` hacia la entrada.
- Los niveles se pueden consultar sin cambiar la orientación de las bandas.
- El plano puede contener posiciones bloqueadas o fuera de servicio.

## Reglas de ubicación con advertencia

Las reglas físicas orientan al operador, pero no bloquean una operación válida:

1. Si se intenta ocupar una posición dejando libre otra más profunda en la
   misma banda y nivel, el sistema solicita confirmación.
2. Si se intenta ocupar un nivel superior sin un bulto de soporte debajo, el
   sistema solicita confirmación.
3. Si se intenta retirar o mover un nivel inferior con otro bulto encima, el
   sistema solicita confirmación.

La confirmación debe quedar asociada al movimiento, al usuario y al
dispositivo. Una advertencia no confirmada no produce cambios.

## Configuración desde oficina

La configuración se realiza normalmente desde PC y no exige que el equipo esté
registrado como tablet. Solo los roles `administrador` y `supervisor` pueden:

- crear cámaras;
- definir bandas, posiciones y niveles;
- revisar el plano antes de guardarlo;
- marcar posiciones fuera de servicio;
- inactivar cámaras sin eliminar su historial.

La creación de una cámara y sus posiciones se realiza en una única transacción.
No se eliminan físicamente cámaras o posiciones con historia operacional.

## Preparación de cargas

Una carga agrupa entre 1 y 26 folios disponibles para una orden de embarque. El
despachador la prepara desde PC y los camareros la ejecutan desde tablet.

La carga conserva un código interno estable, por ejemplo `CAR-000001`, separado
del número de orden de embarque que en el futuro podrá provenir de Suit Export.
Cada folio asignado muestra el código de la carga dentro del plano de estiba.

### Separación física

Estar en la misma cámara no implica que una carga esté separada. El sistema
calcula el mayor grupo conectado de folios de la carga:

- posiciones consecutivas dentro de una banda;
- bandas consecutivas en posiciones equivalentes;
- niveles consecutivos de una misma posición.

El porcentaje agrupado es:

`folios del mayor grupo correlativo / folios pendientes de la carga * 100`

- Menos de 80 %: `en_separacion`.
- Entre 80 % y 99 %: `separada`, indicando cuántos folios faltan.
- 100 %: `separacion_completa`.

El plano debe distinguir el grupo principal, los folios faltantes y los bultos
ajenos que bloquean su salida.

### Envío a andén

Enviar folios desde una cámara al andén es la acción **Despachar**:

- con menos de 80 %, los folios se despachan individualmente;
- con 80 % o más, se puede despachar conjuntamente el grupo correlativo;
- con 100 %, se puede despachar la carga completa;
- una carga queda `despachada` cuando todos sus folios fueron enviados al andén.

Cada despacho libera la posición y registra carga, folio, origen, andén,
usuario, dispositivo y fecha. Los andenes tendrán códigos como `AND-01`.

## Entregas

1. Configuración de cámaras, nomenclatura, plano vertical y advertencias.
2. Configuración de andenes, órdenes de carga, separación y despacho.

