# Reglas de negocio

Estas reglas son obligatorias para la base de datos, los servicios Laravel, la API y la aplicación de tablet.

## 1. Cámaras y posiciones

1. Una cámara debe existir antes de generar sus posiciones.
2. La combinación de cámara, banda, posición y nivel debe ser única.
3. Una posición puede estar activa, bloqueada o fuera de servicio.
4. Solo una posición activa puede recibir un bulto.
5. Una cámara no se elimina físicamente; se desactiva.
6. Una cámara con folios o una sesión abierta no puede desactivarse ni reducirse.
7. Reducir un plano archiva las coordenadas retiradas y conserva todo su historial.
8. La capacidad configurada se obtiene de bandas, posiciones y niveles; la capacidad disponible considera solo posiciones activas.
9. Cada movimiento aceptado incrementa la versión de todas las cámaras afectadas; un traslado incrementa las versiones del origen y del destino.

## 2. Folios

1. El número de folio es único.
2. Un folio representa un único bulto.
3. El bulto puede ser pallet o saldo.
4. Si el folio no existe al ubicarlo, debe crearse automáticamente dentro de la misma transacción.
5. La creación mínima registra número, tipo de bulto, condición SAG conocida o sin información, fecha de ingreso, origen operacional y estado activo.
6. Los datos faltantes pueden completarse posteriormente de forma manual o mediante ERP.
7. Enriquecer un folio nunca cambia su UUID interno ni sus movimientos.
8. Un folio anulado, bloqueado, retirado definitivamente o despachado no puede reactivarse automáticamente.
9. Los folios inválidos se anulan; no se eliminan físicamente.

## 3. Ubicación actual

1. Una posición puede contener un único folio.
2. Un folio puede ocupar una única posición.
3. La ubicación actual y el movimiento que la origina deben confirmarse juntos.
4. Una reubicación cambia el folio entre posiciones diferentes de la misma cámara.
5. Un traslado entre cámaras cambia el folio desde una posición de una cámara hacia una posición de otra cámara.
6. El destino debe estar activo y disponible.
7. Un traslado debe liberar el origen y ocupar el destino dentro de la misma transacción MySQL.
8. Si el traslado falla, la ubicación de origen debe conservarse sin cambios.
9. Un retiro libera la posición y conserva el movimiento histórico.
10. Una reversión se registra como un movimiento nuevo.

## 4. Sesiones de estiba

1. Varios usuarios pueden consultar una cámara simultáneamente.
2. Solo una sesión puede modificar una cámara.
3. Abrir una sesión debe bloquear la cámara de forma atómica.
4. Cada sesión identifica cámara, usuario y dispositivo.
5. Todos los movimientos de edición deben incluir una sesión activa por cada cámara afectada.
6. Un traslado requiere que el mismo usuario y dispositivo posean sesiones activas sobre las cámaras de origen y destino.
7. Los bloqueos de ambas cámaras deben adquirirse en un orden estable para evitar interbloqueos.
8. Si no es posible obtener autorización exclusiva sobre ambas cámaras, el traslado no se ejecuta.
9. Cerrar una sesión libera la cámara.
10. Una modificación posterior crea otra sesión; una sesión cerrada no se reutiliza.
11. Una sesión aparentemente abandonada no se entrega silenciosamente a otro operador.
12. Un supervisor puede cerrarla forzosamente después de verificar la situación.
13. Las operaciones offline asociadas a una sesión cerrada forzosamente pasan a conflicto.

## 5. Movimientos y auditoría

1. Los tipos iniciales son ubicación inicial o ingreso a cámara, reubicación dentro de una cámara, traslado entre cámaras, retiro y reversión.
2. Ingreso a cámara y ubicación inicial son dos nombres para un mismo tipo de movimiento.
3. Cada movimiento registra folio, cámaras y posiciones de origen y destino cuando correspondan, sesiones, usuario, dispositivo, motivo y fechas.
4. Deben conservarse la fecha generada por la tablet y la fecha recibida por el servidor.
5. Los movimientos son inalterables.
6. Ninguna eliminación de folios, posiciones o usuarios puede borrar movimientos.
7. Toda corrección operacional debe dejar una nueva evidencia.
8. El historial debe poder consultarse por folio, cámara, sesión, usuario y periodo.

## 6. Idempotencia y concurrencia

1. Cada operación utiliza un UUID único.
2. Reenviar el mismo UUID debe devolver el resultado original sin repetir el movimiento.
3. La creación automática de folios se resuelve por número de folio único.
4. La base de datos debe impedir doble ocupación incluso si la validación de la aplicación falla.
5. La ubicación, el movimiento, las versiones de todas las cámaras afectadas y la bitácora se actualizan en una transacción MySQL.
6. Ante concurrencia, el servidor central decide y devuelve conflicto a la tablet que perdió la condición válida.

## 7. Operación offline

1. La tablet guarda cada operación antes de considerarla pendiente de sincronización.
2. Las operaciones se envían respetando su orden local.
3. Cada operación incluye UUID, sesiones, folio, cámaras y posiciones de origen y destino, además de las versiones conocidas de todas las cámaras afectadas.
4. El servidor puede aceptar, rechazar o marcar como conflicto.
5. Un conflicto no debe sobrescribir automáticamente la ubicación central.
6. Reiniciar la aplicación no debe perder operaciones pendientes.
7. Un folio nuevo offline se reconoce centralmente por su número y recibe una identidad canónica.
8. Si el folio ya existe en otra ubicación, la operación pasa a conflicto.

## 8. Integración futura con ERP

1. Las tablets nunca consultan directamente el ERP.
2. Laravel sincroniza los datos hacia MySQL mediante procesos en segundo plano.
3. El ERP puede crear o enriquecer folios, pero no modificar ubicaciones ni movimientos.
4. La coincidencia principal se realiza por número de folio.
5. ODBC, si se utiliza, debe ser de solo lectura.
6. La ausencia de integración no impide la operación manual.
7. Toda importación debe registrar origen, fecha, resultado y errores.

## 9. Órdenes de carga

1. Una carga en borrador puede contener entre 0 y 26 folios.
2. Publicar exige entre 1 y 26 folios activos, disponibles y con ubicación actual.
3. Un folio solo puede mantener una asignación de carga vigente.
4. Los borradores reservan sus folios, pero no aparecen en tablets ni en el plano operacional.
5. Una carga pendiente puede modificarse mientras no haya iniciado su separación.
6. Cada mutación exige la versión conocida e incrementa la versión de la carga una sola vez.
7. Una versión desactualizada se rechaza como conflicto y no sobrescribe cambios ajenos.
8. Cancelar una carga libera sus asignaciones actuales, pero conserva eventos por cada folio.
9. La distribución por cámara se calcula desde las ubicaciones actuales y no se almacena como copia.
10. Las cargas y sus eventos no se eliminan físicamente.

## 10. Autorizaciones

| Acción | Operador | Despachador | Supervisor | Administrador | Consulta |
|---|---:|---:|---:|---:|---:|
| Consultar cámaras | Sí | Sí | Sí | Sí | Sí |
| Abrir sesión | Sí | No | Sí | Sí | No |
| Ubicar, reubicar y trasladar | Sí | No | Sí | Sí | No |
| Retirar | Sí | No | Sí | Sí | No |
| Revertir | No | No | Sí | Sí | No |
| Cerrar sesión ajena | No | No | Sí | Sí | No |
| Crear y editar cargas | No | Sí | Sí | Sí | No |
| Consultar cargas operacionales | Sí | Sí | Sí | Sí | Sí |
| Crear cámaras | No | No | Sí | Sí | No |
| Editar o redimensionar cámaras | No | No | No | Sí | No |
| Desactivar o reactivar cámaras | No | No | No | Sí | No |
| Administrar usuarios y dispositivos | No | No | No | Sí | No |

La matriz podrá refinarse, pero cualquier ampliación deberá conservar la trazabilidad.
