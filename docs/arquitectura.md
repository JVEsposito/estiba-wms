# Arquitectura propuesta

## Objetivo arquitectónico

Estiba WMS debe funcionar de forma confiable en tablets Android, mantener integridad transaccional en MySQL y tolerar pérdidas temporales de conectividad dentro de cámaras de frío.

La interfaz puede evolucionar sin alterar las reglas del backend ni el modelo central.

## Componentes

| Componente | Responsabilidad |
|---|---|
| React Native y TypeScript | Aplicación principal para tablets |
| SQLite local | Plano descargado, datos mínimos y cola offline |
| Laravel API REST | Autenticación, reglas, transacciones y sincronización |
| MySQL | Inventario central, ubicaciones y auditoría |
| Panel Laravel | Configuración y supervisión administrativa |
| GitHub Actions | Pruebas y validaciones automáticas |

## Estructura del repositorio

El proyecto se mantendrá como monorepositorio:

- La aplicación Laravel permanece en la raíz.
- La aplicación para tablets se incorporará en la carpeta mobile.
- Las decisiones funcionales y técnicas viven en docs.
- Los flujos automáticos viven en .github/workflows.

## Entidades centrales

### Usuarios y dispositivos

Los usuarios representan a las personas responsables. Los dispositivos representan tablets autorizadas. Una sesión y un movimiento deben identificar ambos.

### Cámaras y posiciones

Las cámaras son entidades configurables. Las posiciones son espacios permanentes pertenecientes a una cámara.

El módulo administrativo debe permitir generar una cuadrícula, previsualizarla y ajustar posiciones individuales. Las posiciones se desactivan o bloquean; no se eliminan cuando tienen historial.

### Folios

Los folios son creados bajo demanda durante la primera ubicación si todavía no existen.

El registro inicial puede ser provisional. Una integración futura podrá enriquecerlo con condición SAG y otros datos sin cambiar su identidad interna.

### Ubicaciones actuales

La ocupación vigente se mantiene separada del historial. Deben existir restricciones únicas para folio y posición.

### Sesiones de estiba

Una sesión entrega el derecho temporal y exclusivo de modificar una cámara. La lectura permanece disponible para otros usuarios.

La sesión conserva usuario, dispositivo, inicio, actividad, cierre y versiones de cámara.

### Movimientos

Los movimientos forman una bitácora inalterable. La ubicación inicial y el ingreso a cámara son la misma operación. Una reubicación ocurre dentro de una cámara y un traslado cambia el bulto entre cámaras, conservando todo su contexto operacional.

### Operaciones de sincronización

Cada comando generado en tablet usa un UUID idempotente. El servidor conserva su resultado para responder de manera consistente ante reintentos.

## Flujo transaccional de ubicación

Una solicitud de ubicación o movimiento debe:

1. Validar usuario, dispositivo y las sesiones de todas las cámaras afectadas.
2. Bloquear las cámaras y posiciones necesarias en un orden estable.
3. Buscar el folio por su número.
4. Crearlo si no existe durante una ubicación inicial.
5. Validar el estado del folio, su origen vigente y la disponibilidad del destino.
6. Crear o actualizar la ubicación actual.
7. Registrar el movimiento.
8. Incrementar las versiones de todas las cámaras afectadas.
9. Registrar el resultado de la operación idempotente.
10. Confirmar toda la transacción.

Si un paso falla, ninguno de los cambios debe persistir.

### Traslado entre cámaras

Un traslado exige sesiones activas del mismo usuario y dispositivo sobre las cámaras de origen y destino. Laravel bloquea ambas cámaras en un orden determinista, verifica la posición de origen, ocupa el destino, libera el origen, registra un único movimiento e incrementa ambas versiones dentro de una sola transacción MySQL.

Si otra persona está editando cualquiera de las cámaras, el traslado queda bloqueado o pasa a conflicto. Nunca se confirma solo una parte del cambio.

## Control de edición por cámara

La apertura de sesión utiliza bloqueo transaccional para impedir dos editores. En un traslado, el operador debe obtener la edición exclusiva de ambas cámaras antes de ejecutar el movimiento.

La tablet enviará señales de actividad cuando tenga conexión. Una sesión sin actividad se marca como potencialmente abandonada, pero requiere cierre explícito o intervención de un supervisor antes de autorizar otro editor.

El plano obtenido por los lectores incluye el usuario editor y la hora de inicio para mostrar una advertencia.

## Sincronización offline

### En la tablet

SQLite mantiene:

- Cámaras descargadas.
- Posiciones.
- Folios necesarios.
- Ubicaciones visibles.
- Sesiones activas.
- Operaciones pendientes.
- Resultados y conflictos.

Cada operación incluye el UUID, las sesiones y versiones conocidas de todas las cámaras afectadas, además de una marca temporal del dispositivo.

### En Laravel

La API recibe lotes ordenados:

1. Busca el UUID de operación.
2. Devuelve el resultado previo si ya fue procesado.
3. Valida sesión y versión.
4. Aplica la regla dentro de una transacción.
5. Registra éxito, rechazo o conflicto.
6. Devuelve la identidad central del folio y las nuevas versiones de las cámaras afectadas.

### Conflictos

Se consideran conflictos, entre otros:

- Posición ocupada después de la descarga local.
- El origen del folio cambió desde la descarga local.
- La cámara de origen o destino está siendo editada por otra persona.
- Sesión cerrada o forzada.
- Versión incompatible en cualquiera de las cámaras afectadas.
- Folio bloqueado o inactivo.

La tablet debe mostrar el conflicto y descargar el estado central. No debe resolverlo sobrescribiendo automáticamente.

## Preparación para ERP

La integración se implementará detrás de una interfaz de fuente de folios.

Adaptadores previstos:

- Fuente manual.
- Fuente de archivos.
- Fuente API o Webservice.
- Fuente ODBC de solo lectura.

El proceso de integración ejecutará trabajos en segundo plano y actualizará MySQL. Las operaciones de estiba nunca dependerán de la disponibilidad del ERP.

El número de folio será la clave de coincidencia empresarial. El UUID de la WMS continuará siendo la identidad técnica central.

## API

La API se versionará bajo api/v1 y se organizará por:

- Autenticación y dispositivos.
- Cámaras y posiciones.
- Sesiones.
- Folios.
- Movimientos.
- Sincronización.
- Administración.

Las validaciones de entrada utilizarán Form Requests y las respuestas utilizarán Resources o contratos equivalentes.

## Seguridad

- HTTPS obligatorio fuera del entorno local.
- Laravel Sanctum para tokens.
- Roles y permisos.
- Tokens asociados a dispositivos.
- Secretos fuera del repositorio.
- ODBC exclusivamente de lectura.
- Movimientos sin eliminación física.
- Copias de seguridad automáticas de MySQL.
- Registro de accesos, errores y cierres forzados.

## Pruebas

Las pruebas del backend deben cubrir:

- Unicidad de folios y posiciones.
- Apertura simultánea de sesión.
- Doble ocupación.
- Reintentos idempotentes.
- Reubicaciones concurrentes.
- Traslado atómico entre cámaras.
- Traslados cruzados y adquisición ordenada de bloqueos.
- Creación automática de folios.
- Cierre forzado.
- Operaciones offline fuera de orden.
- Reversiones.

Las pruebas de integración se ejecutarán sobre MySQL, porque SQLite no reproduce todas las restricciones y conductas de concurrencia del servidor central.

## Evolución

Después de validar el núcleo se incorporarán cargas y despachos. El repaletizaje permanecerá separado hasta que exista un alcance operacional propio.
