# Escrituras solo en la temporada activa

## Regla

Toda escritura sobre un registro operacional ocurre en la temporada global
activa. Las consultas del historial de temporadas anteriores siguen disponibles.

Materiales queda fuera: traspasa su inventario entre temporadas con la migración
auditada de `ServicioMigracionTemporada`.

## Cómo se aplica

| Pieza | Función |
| --- | --- |
| `App\Models\Contracts\PerteneceATemporada` | Contrato del registro: `temporadaOperacionalId()` |
| `App\Models\Concerns\TemporadaPorColumna` | Implementación para modelos con columna `temporada_id` |
| `App\Services\Temporadas\GuardiaTemporadaActiva` | Regla única; lanza `RegistroFueraDeTemporadaActiva` |
| `App\Http\Middleware\AsegurarTemporadaActivaDelRegistro` | Aplica la regla a todo parámetro de ruta en POST, PUT, PATCH y DELETE |

El middleware está en el grupo `api` y corre después de `SubstituteBindings`,
cuando los parámetros ya son modelos. La respuesta es:

```json
HTTP 409
{
  "message": "Este registro pertenece a la temporada 2025-2026, que no está activa. Solo se puede modificar información de la temporada 2026-2027.",
  "codigo": "temporada_no_activa",
  "temporada_registro": "2025-2026",
  "temporada_activa": "2026-2027"
}
```

Los servicios que reciben el registro por otro camino que la ruta llaman a la
guardia directamente:

- `ServicioMovimientoEstiba::ubicar` y `mover`: el folio se busca por número en
  todas las temporadas.
- `ServicioLoteMateriaPrima`: el lote nace en la temporada de su recepción.
- `ServicioCalendarioEmbarques::confirmar`: la carga se crea en la temporada activa.

## Registros controlados

Con columna `temporada_id`: recepción de Romana, Validación MP, lote MP, bin de
retorno packing, proceso de prefrío, carga, embarque, lote SAG, validación de
pallet, guía de despacho de envases, movimiento de envases, plan operacional y
folio (salvo los folios de Materiales).

Por su registro padre: pesaje de envases → recepción; entrega a proceso → lote;
retorno packing → entrega; sublote → retorno; folio de prefrío → proceso;
asignación a carga → carga; incidencia → asignación; resultado SAG → lote SAG;
repaletizaje → folio resultante o conservado; tarea, maniobra y discrepancia →
plan operacional.

## Exentos

`tests/Feature/Architecture/TemporadaActivaRutasTest.php` enumera los modelos
que no se controlan y el motivo: infraestructura física, maestros, accesos,
catálogo de validación (la próxima temporada se prepara antes de activarla),
sesiones de estiba y Materiales.

Una ruta nueva de escritura con un modelo que no esté en ninguna de las dos
listas hace fallar esa prueba.

## Otros cambios

- Editar o corregir una recepción de Romana ya no la traslada a la temporada activa.
- El historial y los contadores de anulaciones de pallets muestran solo la temporada activa.
- Operación ahora y el plano marcan como `bloqueado_otra_temporada` un túnel
  bloqueado por un proceso sin cerrar de otra temporada. Antes figuraba «disponible»
  aunque el prefrío no admitía otro proceso en él. El sistema mantiene las
  posiciones y folios de ese registro en la ocupación operativa hasta cerrar o
  regularizar el proceso; esto no acredita la presencia física de fruta. Los
  contadores `posiciones_sin_cerrar` y `folios_sin_cerrar` los distinguen de
  la operación corriente. El plano muestra el bloqueo sin representar un
  porcentaje de carga, y Operación ahora indica que se resolverá en el cierre
  de temporada, sin enlazar a acciones que hoy responden 409.

## Pendiente de los siguientes PR

- **Cierre de temporada (PR 3):** con este control, lo que quede abierto de la
  temporada anterior ya no se puede cerrar con las acciones normales. El cierre
  y la regularización auditada llegan en el PR 3; no conviene activar una
  temporada productiva nueva antes de ese PR.
- **Correcciones administrativas históricas:** Romana, prefrío y validaciones
  quedan bloqueadas fuera de la temporada activa. Si se necesitan, deben volver
  como una acción específica y auditada.
