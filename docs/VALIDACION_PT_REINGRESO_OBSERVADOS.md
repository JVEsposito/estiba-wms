# Reingreso de folios observados en Validación PT

## Regla operacional

Un resultado `observado` no es terminal. El pallet o saldo puede volver a la misma PDA después de ser corregido y registrar un nuevo intento.

Al consultar nuevamente el folio, la PDA recupera desde el último intento aceptado:

- tipo de bulto;
- cantidad de cajas;
- categoría;
- artículo: especie, variedad, calibre y envase;
- origen: cliente, marca y CSG;
- motivo y observación anteriores.

La información recuperada se precarga en el formulario. El validador puede aprobar la corrección, mantener el folio observado o, cuando su rol lo permite, rechazarlo definitivamente.

## Historial

Cada decisión crea un intento nuevo. El intento observado anterior no se modifica ni se elimina.

```text
Intento 1 · observado
Intento 2 · aprobado
```

El folio de inventario nace únicamente cuando un intento aprobado es aceptado. En ese momento queda pendiente de Prefrío.

## Decisiones terminales

Los resultados `aprobado` y `rechazado` son terminales. Al consultar un folio con una de estas decisiones, la PDA muestra la información guardada en modo de solo lectura y bloquea nuevas acciones.

## Trabajo desconectado

Si la PDA posee una operación local del mismo folio pendiente, con error o en conflicto, no permite registrar otro intento hasta sincronizarla. Esto evita que dos intentos del mismo folio lleguen al servidor fuera de orden.
