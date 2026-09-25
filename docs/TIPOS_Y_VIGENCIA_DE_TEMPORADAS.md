# Tipos y vigencia de temporadas

## Tipos

| Tipo | Uso | Activación | Reportes y selectores operacionales |
| --- | --- | --- | --- |
| `productiva` | Operación real | Permitida, con fechas y prefijo | Sí |
| `prueba` | Ensayos del sistema | Nunca | No |

Las temporadas existentes quedan como productivas al migrar; la migración no
reclasifica ni modifica datos.

## Declarar prueba o volver a productiva

Accesos y temporadas → **Declarar prueba** o **Declarar productiva**.

- Solo sobre una temporada inactiva. La activa no puede declararse de prueba.
- Motivo obligatorio (10 a 500 caracteres).
- Cada cambio queda en `clasificaciones_temporada`, inmutable: tipo anterior,
  tipo nuevo, motivo, usuario y fecha.
- Solo cambia el tipo de esa temporada. No mueve, copia ni borra datos de la
  temporada activa ni de Materiales. Una prueba automatizada compara todas las
  tablas antes y después.
- Volver a productiva exige las mismas condiciones que cualquier productiva.

API: `POST /api/administracion/temporadas/{temporada}/declarar-prueba` y
`.../declarar-productiva`, con `{ "motivo": "..." }`. Requiere
`administrar-accesos`.

Al crear una temporada también puede indicarse `"tipo": "prueba"`. Después, el
tipo solo cambia con las acciones anteriores.
El formulario de Accesos permite elegir el tipo al crear; las fechas son
opcionales para una temporada de prueba y no se ofrece activarla. Al editar,
el tipo se muestra fijo y se cambia con las acciones auditadas.

## Temporadas productivas

- **Fechas obligatorias**, y no pueden cruzarse con las de otra temporada
  productiva. `fecha_fin` puede extenderse mientras no se cruce.
- **Prefijo documental** de 2 a 6 letras o números, único (por ejemplo `T27`).
  Identificará la temporada en la numeración de sus documentos. Se exige para
  activarla.

Si una temporada de ensayo impide guardar la real porque sus fechas se cruzan,
el mensaje lo indica: basta con declararla de prueba.

## Temporadas de prueba

- No se activan: ni desde Accesos ni con una migración que active el destino.
- No aparecen en los selectores de Romana, cuenta corriente de envases,
  Validación de pallets, defectos MP, trazabilidad de lotes ni en el panel
  gerencial (que además rechaza su `temporada_id`).
- Siguen visibles en Accesos y temporadas, en el catálogo de validación y en
  Materiales, donde su historial se conserva.

## Pendiente

- **PR 4 (numeración):** el prefijo quedará fijo después de emitir el primer
  documento con él. Hoy puede editarse.
- **PR 7:** vaciado de temporadas de prueba.
