# Alcance del MVP

## Objetivo

Construir una aplicación robusta y amigable para tablets que permita administrar cámaras, visualizar su plano de estiba, ubicar bultos mediante folios y conservar la trazabilidad completa de cada movimiento, incluso cuando exista conectividad intermitente.

El sistema debe ser simple para el operador y estricto en la protección del inventario.

## Prioridad del MVP

El primer producto se concentrará en:

1. Configuración de cámaras y posiciones.
2. Consulta del plano de estiba.
3. Creación automática de folios al ubicarlos.
4. Ubicación, reubicación y retiro de bultos.
5. Sesiones exclusivas de edición por cámara.
6. Historial de movimientos.
7. Operación offline y sincronización.
8. Detección y resolución de conflictos.

## Incluido

### Usuarios y dispositivos

- Autenticación.
- Roles de administrador, supervisor, operador y consulta.
- Registro y autorización de tablets.
- Identificación del usuario y dispositivo en cada sesión y movimiento.

### Cámaras y posiciones

- Crear y editar cámaras.
- Definir filas o bandas, profundidades o posiciones y niveles.
- Previsualizar y generar la cuadrícula.
- Ajustar posiciones individuales.
- Activar, bloquear o desactivar posiciones.
- Desactivar cámaras sin eliminar su historial.
- Calcular capacidad desde las posiciones activas.

### Folios

- Buscar por número de folio.
- Crear automáticamente un folio que no exista al realizar su primera ubicación.
- Identificar el bulto como pallet o saldo.
- Registrar condición SAG, estado operacional, fecha de ingreso y estado activo.
- Mantener datos descriptivos opcionales.
- Consultar ubicación e historial.
- Corregir información mediante permisos controlados, sin eliminar trazabilidad.

No existirá un formulario operacional separado para dar de alta folios.

### Estiba y movimientos

- Consultar una cámara sin bloquearla.
- Obtener una sesión exclusiva para modificarla.
- Advertir quién está editando y desde qué momento.
- Ubicar un folio en una posición libre.
- Reubicar un folio.
- Retirar un folio de su posición.
- Revertir movimientos mediante una nueva operación autorizada.
- Mantener origen, destino, usuario, dispositivo, sesión y fechas.

### Operación offline

- Descargar el plano necesario.
- Conservar la sesión en la tablet.
- Registrar operaciones localmente.
- Sincronizar operaciones en orden.
- Evitar ejecuciones duplicadas.
- Detectar conflictos de versión, posición o folio.
- Mantener visibles las operaciones pendientes o rechazadas.

## Preparado para el futuro

El modelo deberá admitir una integración posterior con cualquier ERP, incluido el utilizado por la empresa, sin modificar el núcleo de estibas.

Se dejarán previstos:

- Sistema de origen.
- Identificador externo.
- Estado de vinculación.
- Fechas de actualización y sincronización.
- Adaptadores para API, Webservice, ODBC o archivos.
- Importación manual o masiva como alternativa.

La WMS será la fuente oficial de ubicaciones y movimientos. El ERP podrá enriquecer los datos descriptivos de un folio existente mediante su número único.

## Fuera del MVP inicial

- Integración automática con ERP.
- Repaletizaje y consolidación de saldos.
- Balance o transformación de cajas.
- Registros de temperatura.
- Impresión de etiquetas.
- Integraciones con impresoras o ERP productivo.
- Operación entre múltiples plantas.
- Cargas y despachos en la primera entrega.

## Módulo posterior de cargas

Una vez estabilizados estibas y movimientos se incorporará:

- Creación de cargas.
- Asignación de entre 1 y 26 bultos.
- Validación.
- Confirmación de despacho.
- Liberación de posiciones.
- Historial de salida.

## Criterios de término

El MVP se considerará operativo cuando:

- Dos usuarios puedan consultar la misma cámara.
- Solo un usuario pueda modificarla.
- No sea posible duplicar una posición ni ubicar un folio dos veces.
- Cada movimiento conserve trazabilidad completa.
- Un folio inexistente pueda crearse durante su ubicación.
- Una operación repetida no vuelva a ejecutarse.
- La tablet conserve movimientos al perder conexión o reiniciarse.
- Los conflictos no modifiquen silenciosamente el inventario central.
- El plano digital concuerde con una prueba física controlada.
