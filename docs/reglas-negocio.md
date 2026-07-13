# Reglas de negocio

Estas reglas son obligatorias para la base de datos, los servicios Laravel, la API y la aplicación de tablet.

## 1. Cámaras y posiciones

1. Una cámara debe existir antes de generar sus posiciones.
2. La combinación de cámara, fila o banda, profundidad o posición y nivel debe ser única.
3. Una posición puede estar activa, bloqueada o fuera de servicio.
4. Solo una posición activa puede recibir un bulto.
5. Una cámara con historial no se elimina; se desactiva.
6. La capacidad se calcula desde las posiciones activas y no desde un total mantenido manualmente.
7. Cada movimiento aceptado incrementa la versión del plano de la cámara.

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
4. Una reubicación debe tener origen y destino diferentes.
5. El destino debe pertenecer a la cámara editada y estar disponible.
6. Un retiro libera la posición y conserva el movimiento histórico.
7. Una reversión se registra como un movimiento nuevo.

## 4. Sesiones de estiba

1. Varios usuarios pueden consultar una cámara simultáneamente.
2. Solo una sesión puede modificar una cámara.
3. Abrir una sesión debe bloquear la cámara de forma atómica.
4. Cada sesión identifica cámara, usuario y dispositivo.
5. Todos los movimientos de edición deben incluir una sesión activa.
6. Cerrar una sesión libera la cámara.
7. Una modificación posterior crea otra sesión; una sesión cerrada no se reutiliza.
8. Una sesión aparentemente abandonada no se entrega silenciosamente a otro operador.
9. Un supervisor puede cerrarla forzosamente después de verificar la situación.
10. Las operaciones offline asociadas a una sesión cerrada forzosamente pasan a conflicto.

## 5. Movimientos y auditoría

1. Los tipos iniciales son ingreso a cámara, ubicación, reubicación, retiro y reversión.
2. Cada movimiento registra folio, cámara, origen, destino, sesión, usuario, dispositivo, motivo y fechas.
3. Deben conservarse la fecha generada por la tablet y la fecha recibida por el servidor.
4. Los movimientos son inalterables.
5. Ninguna eliminación de folios, posiciones o usuarios puede borrar movimientos.
6. Toda corrección operacional debe dejar una nueva evidencia.
7. El historial debe poder consultarse por folio, cámara, sesión, usuario y periodo.

## 6. Idempotencia y concurrencia

1. Cada operación utiliza un UUID único.
2. Reenviar el mismo UUID debe devolver el resultado original sin repetir el movimiento.
3. La creación automática de folios se resuelve por número de folio único.
4. La base de datos debe impedir doble ocupación incluso si la validación de la aplicación falla.
5. La ubicación, movimiento, versión de cámara y bitácora se actualizan en una transacción MySQL.
6. Ante concurrencia, el servidor central decide y devuelve conflicto a la tablet que perdió la condición válida.

## 7. Operación offline

1. La tablet guarda cada operación antes de considerarla pendiente de sincronización.
2. Las operaciones se envían respetando su orden local.
3. Cada operación incluye UUID, sesión, folio, origen, destino y versión conocida de la cámara.
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

## 9. Autorizaciones

| Acción | Operador | Supervisor | Administrador | Consulta |
|---|---:|---:|---:|---:|
| Consultar cámaras | Sí | Sí | Sí | Sí |
| Abrir sesión | Sí | Sí | Sí | No |
| Ubicar y mover | Sí | Sí | Sí | No |
| Retirar | Sí | Sí | Sí | No |
| Revertir | No | Sí | Sí | No |
| Cerrar sesión ajena | No | Sí | Sí | No |
| Configurar cámaras | No | Sí | Sí | No |
| Administrar usuarios y dispositivos | No | No | Sí | No |

La matriz podrá refinarse, pero cualquier ampliación deberá conservar la trazabilidad.
