# Arbitraje global de maniobras

## Propósito

El servidor decide qué maniobras pueden entrar juntas en la frontera operacional. La decisión deja de depender del orden en que cada generador creó sus tareas o del momento en que una tablet intentó tomarlas.

La unidad de arbitraje es la maniobra completa, incluidos todos sus objetivos y pasos físicos. Una maniobra multiobjetivo compite usando su prioridad dominante y su beneficio agregado.

## Precedencia

La comparación es determinista y se aplica en este orden:

1. realidad física ya iniciada o pausada por discrepancia;
2. prioridad operacional: `critica`, `urgente`, `alta`, `normal`;
3. objetivo dominante dentro de la misma prioridad: evacuación de emergencia, despacho directo con camión, segregación de retenidos y operación normal;
4. beneficio neto: `beneficio_estimado - costo_movimientos - riesgo_operacional`;
5. antigüedad de la maniobra;
6. identificador estable como último desempate.

Un beneficio alto nunca puede desplazar una prioridad superior. Tampoco se generan movimientos para mejorar solamente la apariencia del plano: el beneficio proviene de objetivos operacionales ya materializados.

## Exclusión de recursos

Antes de publicar cada candidata, el árbitro compara:

- pallets involucrados;
- posiciones de destino;
- bandas y niveles protegidos por una maniobra cerrada.

La candidata de menor precedencia queda `excluida_conflicto` y conserva el identificador de la maniobra que ganó cada recurso. Las restricciones únicas de reservas continúan siendo la última defensa transaccional al asumir y ejecutar.

## Frontera corta

Con la configuración predeterminada:

- hasta tres maniobras compatibles quedan `seleccionada`;
- una cuarta puede quedar `alternativa`, visible pero sin reservas y no asumible;
- el resto permanece `fuera_frontera` hasta el siguiente cambio del estado autoritativo.

Las maniobras en ejecución consumen capacidad y prevalecen sobre toda simulación. Una discrepancia pausada conserva sus recursos porque la realidad física ya pudo cambiar.

Los retiros desde REPA quedan `fuera_planificador`: siguen disponibles por la regla propia del buffer y no consumen los tres cupos del planificador global. Sus recursos sí se respetan cuando la maniobra ya comenzó.

## Auditoría e idempotencia

Cada estado relevante produce un `snapshot_version` SHA-256 y un ciclo persistido en:

- `ciclos_arbitraje_maniobras`;
- `decisiones_arbitraje_maniobras`.

El ciclo registra orden, decisión, puntaje, beneficio neto, motivo y conflictos. Refrescar la bandeja sin cambios reutiliza el mismo ciclo; un cambio de versión, estado, prioridad, objetivo o recurso genera uno nuevo.

## Contrato con tablet

La bandeja `GET /api/tareas-movimiento?asignacion=disponibles`:

- publica únicamente seleccionadas y la alternativa global, además de labores expresamente fuera del planificador;
- respeta el orden decidido por el servidor;
- expone el resumen del ciclo y la decisión de cada maniobra.

La tablet muestra la alternativa como espera no accionable. Aunque un cliente antiguo intente tomarla, el servidor recalcula el ciclo dentro de la operación y rechaza la toma si no ganó la frontera vigente.

Este incremento no adelanta el siguiente paso del roadmap: la frontera física dinámica continuará resolviendo destinos próximos y recalculando únicamente el sufijo no iniciado.
