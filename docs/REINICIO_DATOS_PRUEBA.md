# Reinicio controlado de datos de prueba

La oficina **Accesos y temporadas** permite que un administrador deje en cero
la operación de la temporada global activa para los dominios:

- Frigorífico / producto terminado.
- Materia Prima, incluida Romana, lotización, hidrocooler y envases.

## Datos eliminados

- Recepciones y eventos de Romana.
- Validaciones MP, segmentos, lotes, hidrocooler y asignaciones a cámara.
- Movimientos y guías de despacho de envases.
- Folios `pallet` y `saldo`, validaciones PT, cargas, Prefrío, ubicaciones y
  movimientos de cámara correspondientes a la temporada activa.
- Notificaciones y lecturas vinculadas exclusivamente con esos registros.

Los correlativos globales se conservan para evitar reutilizar identificadores
que todavía puedan existir en el historial de otra temporada.

## Datos protegidos

El reinicio no elimina ni desactiva:

- La temporada global activa.
- Catálogos, clientes, productores, usuarios, perfiles o dispositivos.
- Cámaras, posiciones, túneles, andenes ni perfiles de impresión.
- Ítems, recepciones, folios, existencias, movimientos, despachos,
  transformaciones o cualquier otro dato de Bodega / Materiales.

Antes y después de la transacción se comparan conteos de protección de Bodega
y catálogos. Una relación cruzada o cualquier diferencia provoca la reversión
completa del reinicio.

## Autorización y auditoría

Solo una cuenta activa con rol base `administrador` puede previsualizar o
ejecutar el reinicio. La confirmación requiere:

1. Motivo de al menos 10 caracteres.
2. Contraseña actual del administrador.
3. Frase exacta `REINICIAR {CODIGO_TEMPORADA}`.
4. Confirmación explícita de exclusión de Bodega.
5. Confirmación explícita de conservación de temporada y catálogos.

Cada ejecución exitosa crea un registro inmutable en
`reinicios_operacionales`, con usuario, motivo y resúmenes antes, eliminados y
después. `operacion_id` hace que una repetición de la misma solicitud sea
idempotente.
