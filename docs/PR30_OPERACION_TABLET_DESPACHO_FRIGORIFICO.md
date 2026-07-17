# PR #30 — Operación tablet del despacho frigorífico

Este PR incorpora a la APK la ejecución física de las cargas publicadas. No crea
notificaciones persistentes ni polling de cargas; esas piezas pertenecen al PR
#31. La cola se actualiza manualmente y al confirmar una operación.

## Bandeja compartida

Los perfiles habilitados para frío ven todas las cargas publicadas y pueden
filtrarlas por urgencia, preparación o incidencias. La tarea no se adjudica de
forma exclusiva a una persona. La exclusión ocurre únicamente cuando un
camarero abre la sesión de la cámara que va a modificar.

## Ruta vertical

`GET /api/cargas/{carga}/plan-extraccion` calcula la secuencia con estas reglas:

- las bandas se representan verticalmente;
- `P01` corresponde al fondo y las posiciones mayores quedan más cerca de la entrada;
- un folio solo está accesible si no hay otro bulto delante en la misma banda y nivel;
- después de sugerir un folio, el algoritmo simula su retiro antes de calcular el siguiente;
- un folio con incidencia se excluye de la ruta, pero continúa actuando como bloqueo físico;
- si una banda queda bloqueada, el algoritmo continúa con una banda accesible.

La APK resalta en ámbar todos los folios de la carga y en verde el siguiente
folio sugerido. Un folio bloqueado muestra los bultos que están delante.

## Incidencias

El camarero selecciona un motivo rápido y puede agregar una descripción. El
reporte exige una sesión propia y el bloqueo de la cámara. Al confirmarlo, el
folio queda fuera de la ruta y la APK vuelve a consultar la carga. La resolución
comercial sigue realizándose desde `/oficina/cargas`.

## Envío a andén

El operador puede enviar el folio seleccionado o ejecutar la ruta completa. La
acción masiva conserva una operación idempotente por folio, vuelve a consultar
la versión de la cámara antes de cada retiro y se detiene ante el primer
conflicto. Los retiros ya confirmados no se repiten ni se revierten de forma
implícita.

Si la concentración es inferior al 80 %, la APK advierte y solicita confirmación,
pero no impide continuar. Las advertencias físicas del backend también deben ser
confirmadas explícitamente.

## Validación manual sugerida

1. Publicar desde oficina una carga con folios en dos bandas.
2. Iniciar sesión en la APK con un `camarero_frio`.
3. Abrir **Cargas** y comprobar la prioridad y el porcentaje de concentración.
4. Verificar que la ruta empieza por la posición más cercana a la entrada.
5. Abrir el folio sugerido en el plano y moverlo a la zona de concentración.
6. Reportar una incidencia y comprobar que otra banda pasa a ser la siguiente.
7. Seleccionar un andén y enviar un folio.
8. Ejecutar **Enviar ruta completa** y verificar los retiros en MySQL.
9. Confirmar desde oficina que la carga queda lista para cerrar la salida.

