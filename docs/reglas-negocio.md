# Reglas de negocio

Estas reglas aplican a la base de datos, los servicios Laravel, la API, las oficinas web y la aplicación móvil. Una restricción visual nunca sustituye la validación del backend.

## 1. Temporadas

1. Debe existir una temporada global activa para iniciar operaciones estacionales.
2. Solo Accesos puede crear, editar, activar o migrar temporadas.
3. Cada proceso o documento conserva la temporada con la cual fue creado.
4. Activar una nueva temporada no modifica registros históricos.
5. Validación, Materiales, Romana, Frigorífico, Prefrío, Cargas, Envases y Despachos deben respetar el aislamiento por temporada definido para su dominio.
6. La migración copia únicamente catálogos estacionales autorizados.
7. El inventario vivo de Materiales solo se migra de manera explícita y transaccional.
8. No se migra inventario cuando existen reservas o despachos de materiales abiertos.
9. Recepciones, validaciones, procesos, cargas y movimientos históricos no se copian como si fueran operaciones nuevas.
10. Cada migración conserva auditoría de su ejecución y de los folios trasladados.

## 2. Clientes y proveedores

1. Un cliente operacional posee un único maestro global.
2. Validación y Materiales pueden asociar configuraciones estacionales al mismo cliente global.
3. Desactivar una configuración estacional no elimina el cliente ni sus documentos históricos.
4. Romana y los documentos contractuales guardan snapshots de nombre y código.
5. Un cambio posterior del maestro no modifica esos snapshots.
6. Los proveedores de Materiales pueden asociarse a varios clientes y viceversa.
7. Mover o fusionar maestros debe preservar aliases e historia; no se reescriben documentos confirmados.

## 3. Romana

1. Una recepción se identifica mediante un correlativo `REC-AAMM-####` asignado al crear el expediente.
2. El correlativo es propio de Romana y no es un folio de Frigorífico.
3. La guía no puede repetirse para el mismo cliente y temporada.
4. La recepción registra temporada, cliente, transporte, conductor, guía, servicio, envases y peso bruto.
5. Confirmar el ingreso congela los antecedentes contractuales que ya no deben editarse.
6. La tara debe ser positiva y menor que el peso bruto.
7. El peso neto se calcula al cerrar.
8. Una recepción cerrada no vuelve a estados anteriores.
9. Los reintentos usan UUID e idempotencia.
10. El Aviso de Recibo solo se emite para una recepción cerrada.
11. Romana no crea folios, lotes, validaciones PT, procesos de Prefrío ni ubicaciones.
12. Los eventos de Romana no se eliminan físicamente.

## 4. Validación MP

1. Solo se validan recepciones elegibles de la temporada correspondiente.
2. La recepción se busca mediante su número `REC-*`.
3. Una recepción puede estar tomada por un único validador MP a la vez.
4. La toma debe impedir una segunda validación concurrente.
5. Cliente, temporada, guía, transporte y envases declarados se heredan desde Romana.
6. El validador registra cantidades reales de bins, totes y esponjas.
7. Una diferencia con la declaración se conserva como evidencia y no bloquea automáticamente el flujo.
8. La revisión de tarjas es visual; no se utiliza como identidad del lote.
9. Una recepción puede contener fruta o exclusivamente envases.
10. Las segregaciones solo pueden usar tipos de envase declarados y no pueden duplicar la misma línea incompatible.
11. Los segmentos resultantes permanecen `pendiente_lote` hasta que exista un flujo formal de creación de lotes.
12. Validación MP no crea folios ni movimientos del dominio Frigorífico.
13. La temporada se hereda de la recepción aunque posteriormente cambie la temporada global activa.

## 5. Cuenta corriente y despacho de envases

1. Bins, totes y esponjas se registran como líneas independientes.
2. Cada movimiento identifica temporada, cliente, tipo de envase, cantidad, propiedad y origen.
3. La propiedad puede ser propia, del cliente o arrendada.
4. Las cantidades utilizan movimientos firmados; una reversa se registra como un movimiento compensatorio.
5. No se elimina un movimiento para corregir una cuenta.
6. Una guía de despacho se identifica como `GDE-AAMM-NNNN`.
7. Una guía en borrador no descuenta existencia.
8. Confirmar una guía descuenta existencia y genera el movimiento correspondiente en la cuenta corriente.
9. Una guía confirmada es inmutable.
10. Anular una guía genera movimientos compensatorios y conserva la guía original.
11. Los envases arrendados conservan la cuenta del arrendador aunque salgan hacia otro cliente.
12. Una guía histórica no puede confirmarse ni anularse desde otra temporada.
13. Las líneas duplicadas o un consumo conjunto superior al stock deben rechazarse.
14. La guía es un documento operacional interno y no un DTE legal.
15. Cada mutación crítica utiliza UUID y hash de payload.

## 6. Validación de pallets/PT

1. Validación PT y Validación MP son procesos distintos.
2. Validación PT utiliza la temporada global activa y su catálogo estacional.
3. La captura selecciona categoría, artículo y origen activos de la misma temporada.
4. El catálogo jerárquico se proyecta a artículos, orígenes y combinaciones compatibles para la PDA.
5. Una importación con errores no aplica filas parciales.
6. La previsualización no modifica el catálogo.
7. Confirmar una importación debe ser transaccional e idempotente.
8. Aprobar crea el folio dentro de la misma transacción.
9. El folio aprobado nace `pendiente_prefrio`, térmicamente no habilitado y fuera de una cámara.
10. Observar o rechazar no crea inventario.
11. Una observación permite un intento posterior.
12. Una decisión terminal no se reemplaza silenciosamente.
13. El mismo UUID con el mismo payload devuelve el resultado existente.
14. El mismo UUID con datos diferentes produce conflicto.
15. Los snapshots conservan el catálogo utilizado aunque los maestros cambien.

## 7. Prefrío

1. Los túneles son activos independientes de las cámaras.
2. Solo el administrador modifica la estructura de un túnel.
3. Un túnel admite un único proceso activo.
4. Un folio no participa simultáneamente en dos procesos activos.
5. Una posición del túnel contiene un único folio dentro del proceso.
6. Solo producto elegible puede cargarse en Prefrío.
7. Confirmar armado cierra la composición del proceso.
8. Iniciar marca los folios como `en_proceso`.
9. Las lecturas y eventos conservan UUID, usuario, dispositivo y fecha operacional.
10. Enviar a verificación no equivale a aprobar.
11. Aprobar establece `prefrio_aprobado` y habilita el almacenamiento.
12. La aprobación no crea la ubicación ni deja automáticamente el folio disponible para cargas.
13. Reproceso retiene el folio y conserva la relación con el proceso fallido.
14. Cancelar un proceso iniciado no afirma un tratamiento conforme.
15. La primera ubicación en cámara de un folio aprobado lo promueve a disponible.
16. Una condición térmica heredada puede habilitar rutas autorizadas distintas de Prefrío, dejando registrada su fuente.

## 8. Cámaras, posiciones y sesiones

1. Una cámara debe existir antes de generar posiciones.
2. Cámara, banda, posición y nivel forman una coordenada única.
3. Una posición puede estar activa, bloqueada o fuera de servicio.
4. Una cámara con inventario o una sesión abierta no se reduce ni desactiva de forma destructiva.
5. Reducir un plano archiva coordenadas retiradas y conserva su historia.
6. Varios usuarios pueden consultar una cámara.
7. Solo una sesión puede editar una cámara a la vez.
8. La sesión identifica cámara, usuario, dispositivo, inicio, actividad y cierre.
9. Un supervisor solo puede cerrar forzosamente sesiones de su ámbito; el administrador puede cerrar cualquiera.
10. El cierre forzoso exige motivo y deja auditoría.
11. Un traslado requiere sesiones válidas sobre origen y destino cuando corresponda.
12. Los bloqueos se adquieren en un orden estable para evitar interbloqueos.
13. Cada movimiento incrementa las versiones de las cámaras afectadas.

## 9. Folios y ubicaciones

1. El número de folio es único.
2. Un folio representa un bulto de producto o material.
3. Pallet y saldo son tipos de producto; material posee una ficha de inventario adicional.
4. Los folios PT nacen normalmente en Validación.
5. Una creación durante la primera ubicación se reserva para Materiales o una contingencia autorizada.
6. La consulta previa recupera los datos existentes y evita reingresarlos desde Cámaras.
7. Un folio conserva una única ubicación actual.
8. En cámaras de producto una posición contiene un único folio.
9. En cámaras de materiales una posición puede contener varios folios si pertenecen al mismo cliente.
10. No se mezclan clientes dentro de una posición compartida de Materiales.
11. Ubicación actual y movimiento se confirman juntos.
12. Un traslado libera el origen y ocupa el destino en una única transacción.
13. Si una operación falla, el origen se conserva.
14. Un retiro libera la ubicación y mantiene el historial.
15. Un folio anulado, bloqueado, retirado definitivamente o despachado no se reactiva automáticamente.
16. Los folios y movimientos históricos no se eliminan físicamente.

## 10. Materiales

1. Los ítems pertenecen a una temporada y cliente.
2. Un mismo código puede existir bajo clientes diferentes cuando la configuración lo permite.
3. La unidad de medida de un ítem con inventario histórico no cambia libremente.
4. La importación de catálogo no crea folios, cantidades, reservas ni movimientos.
5. Una planilla con errores no se confirma parcialmente.
6. Un cambio concurrente posterior a la previsualización obliga a generar una nueva previsualización.
7. Cada ficha conserva cantidad inicial, actual, reservada y disponible.
8. Los retiros parciales mantienen el folio ubicado hasta agotar la cantidad.
9. FIFO es una sugerencia operacional; una excepción debe quedar registrada.
10. Los destinos y centros de costo se guardan como snapshot del retiro.
11. Un despacho abierto reserva cantidades y bloquea una migración de temporada.
12. Las notificaciones operacionales son idempotentes.
13. Corregir el código de un ítem estibado requiere administrador o supervisor de Materiales, motivo e idempotencia.
14. La corrección no cambia cliente ni unidad de medida.
15. No se corrige un folio con reservas activas o retiros previos.
16. La corrección genera asientos de kardex que explican el antes y el después.

## 11. Cargas y despacho de producto

1. Una carga en borrador contiene entre 0 y 26 folios.
2. Publicar exige entre 1 y 26 folios elegibles y ubicados.
3. Un folio solo mantiene una asignación de carga vigente.
4. Los borradores reservan sus folios, pero no se consideran despachados.
5. La distribución por cámara se calcula desde las ubicaciones actuales.
6. La separación genera tareas operacionales.
7. Las incidencias se registran y resuelven sin borrar el evento original.
8. Enviar un folio a andén genera el movimiento físico correspondiente.
9. El cierre exige completar la salida documental definida por el dominio.
10. Cancelar libera asignaciones vigentes y conserva los eventos.
11. Una carga histórica conserva su temporada.
12. Las cargas y eventos no se eliminan físicamente.

## 12. Idempotencia y concurrencia

1. Cada operación crítica utiliza un UUID.
2. El servidor conserva el hash del payload normalizado.
3. Reenviar el mismo UUID y payload devuelve el resultado original.
4. Reutilizar el UUID con otro contenido genera conflicto.
5. Las operaciones leen y validan versiones conocidas.
6. La base de datos impide doble ocupación, correlativos duplicados y relaciones incompatibles.
7. Los modelos afectados se bloquean dentro de una transacción.
8. Ante concurrencia, el servidor central decide.
9. Una operación rechazada o en conflicto no deja cambios parciales.

## 13. Operación offline

1. El comando se guarda localmente antes de transmitirse.
2. El UUID se mantiene durante todos los reintentos.
3. Los comandos se envían en el orden definido por el módulo.
4. Reiniciar o cerrar sesión no debe borrar una bandeja pendiente.
5. Un conflicto nunca sobrescribe automáticamente el estado central.
6. Validación PT conserva catálogo y capturas pendientes.
7. Prefrío conserva túneles, procesos, folios elegibles y comandos.
8. Un error o conflicto de Prefrío detiene las acciones posteriores del mismo proceso.
9. Los módulos sin bandeja offline deben informar claramente la dependencia de conexión.

## 14. Auditoría y correcciones

1. Los movimientos físicos son inalterables.
2. Los eventos terminales no se eliminan.
3. Las anulaciones generan evidencia o movimientos compensatorios.
4. Los documentos contractuales conservan snapshots.
5. Toda corrección identifica actor, fecha, motivo y valores relevantes.
6. La historia se consulta por temporada y dominio sin mezclarla con la operación activa.
7. Telescope es una herramienta local de diagnóstico y no una bitácora operacional de producción.

## 15. Integración ERP

1. Las tablets nunca consultan directamente el ERP.
2. La WMS mantiene la autoridad de ubicaciones, movimientos y procesos confirmados.
3. El ERP puede aportar maestros o datos descriptivos mediante adaptadores.
4. ODBC, cuando se incorpore, será de solo lectura.
5. Una falla del ERP no debe impedir una operación manual autorizada.
6. Toda importación conserva origen, fecha, resultado y errores.

## 16. Autorización

Los roles vigentes son:

```text
administrador
supervisor_frio
supervisor_materiales
despachador
operador_prefrio
operador_romana
camarero_frio
camarero_materiales
validador
validador_mp
consulta
```

Las capacidades se calculan en el backend. Cada endpoint debe exigir el Gate correspondiente y cada servicio debe volver a validar las reglas críticas del dominio.
