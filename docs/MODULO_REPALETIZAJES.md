# Módulo de Repaletizajes

## Propósito

Un repaletizaje consolida cajas de dos o más folios tipo `saldo` en un único folio resultante. El resultado puede ser un pallet completo o un saldo consolidado.

## Identificación del resultado

El operador elige una de dos estrategias:

- **Conservar folio:** uno de los saldos participantes conserva su número y debe aportar todas sus cajas al resultado.
- **Otro folio:** el operador escanea o escribe un número que todavía no exista en Estiba WMS.

El número solo identifica el resultado. Cliente, especie, marca, condición térmica y demás especificaciones se derivan de los folios que aportaron cajas.

## Reglas de compatibilidad

Una repa se bloquea sin posibilidad de excepción cuando existe diferencia de:

- cliente;
- especie;
- marca;
- estado térmico.

Las diferencias de variedad, calibre, envase, categoría, CSG, predio o cuartel no bloquean. El sistema muestra una advertencia y registra el campo como `MIX`, conservando la composición por folio y cantidad aportada.

## Estado térmico

Solo se admiten dos contextos:

- todos los saldos en `pendiente_prefrio`: resultado y residuales permanecen pendientes de prefrío;
- todos los saldos en `prefrio_aprobado`: resultado y residuales quedan disponibles.

No se admiten folios con prefrío activo, retenidos, en reproceso ni una combinación de estados térmicos.

## Cantidades

- Un resultado `pallet` debe alcanzar exactamente la capacidad indicada.
- Un resultado `saldo` debe quedar bajo esa capacidad cuando esta se informa.
- Cada origen aporta entre una caja y su cantidad disponible.
- Los orígenes totalmente consumidos quedan agotados e inactivos.
- Los orígenes parcialmente consumidos permanecen como saldo con su cantidad residual.

## Trazabilidad

Cada operación registra:

- código `REPA-AAAA-NNNNNN`;
- UUID idempotente;
- folio resultante y estrategia aplicada;
- cantidad anterior, aportada y posterior de cada origen;
- especificaciones originales y resultantes;
- campos MIX y composición exacta;
- operador, dispositivo, fecha y observación;
- snapshots para una anulación controlada.

## Anulación

Solo supervisión o administración puede anular. La anulación se bloquea cuando un folio involucrado ya posee cargas, reservas, movimientos o procesos de prefrío posteriores. Cuando procede, restaura los saldos y ubicaciones originales; un folio nuevo resultante queda anulado e inactivo.

## Interfaces

- **Oficina:** `/oficina/validacion/repaletizajes`
- **PDA:** `Validación → Repaletizajes`

La confirmación requiere conexión al servidor porque actualiza varios folios en una única transacción.
