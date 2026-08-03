# Custodia distribuida de materiales

## Regla operacional

La entrega desde Bodega hacia Packing, Frigorífico, Mantención, Calidad u otro centro de costo es una **transferencia interna de custodia**. No reduce la existencia total de la empresa.

Solo estas operaciones cambian el total vigente:

- consumo;
- ajuste positivo o negativo debidamente autorizado;
- operaciones históricas de recepción, transformación o reversa que ya modificaban el inventario.

## Fuente única de saldo

La fuente operacional es `saldos_materiales_almacenes`.

```text
Folio material
├── Bodega Central: cantidad y reserva
├── Packing: cantidad y reserva
└── Frigorífico: cantidad y reserva
```

La restricción única es:

```text
UNIQUE (folio_id, almacen_material_id)
```

`folios_materiales.cantidad_actual` y `cantidad_reservada` permanecen temporalmente como una **proyección cacheada de compatibilidad**. Nunca se distribuyen de forma independiente: toda operación nueva modifica primero el saldo concreto y luego reconstruye ambos totales mediante la suma de los registros hijos dentro de la misma transacción.

Los servicios históricos que todavía escriben el total pasan por un adaptador controlado que aplica la diferencia exclusivamente al saldo de Bodega Central y valida nuevamente la igualdad:

```text
folios_materiales.cantidad_actual
    = SUM(saldos_materiales_almacenes.cantidad_actual)
```

## Concurrencia

Toda operación se ejecuta mediante transacción y bloqueo pesimista.

El orden de adquisición es estable:

1. folios involucrados, ordenados por identificador;
2. almacenes involucrados, ordenados por identificador;
3. saldos involucrados, ordenados por almacén y folio;
4. movimiento inmutable y proyección global.

La creación concurrente de un saldo destino utiliza el índice único, `insertOrIgnore` y una lectura posterior con `FOR UPDATE`.

Cada saldo posee `version`, incrementada al cambiar cantidad, reserva o ubicación.

## Invariantes

La base y los servicios protegen:

```text
cantidad_actual >= 0
cantidad_reservada >= 0
cantidad_reservada <= cantidad_actual
posición requiere cámara
```

Además:

- un almacén virtual no admite cámara ni posición;
- una bodega física que exige ubicación solo es disponible cuando posee una cámara activa de Materiales;
- una reserva identifica `saldo_material_almacen_id`, no solamente el folio;
- las reservas de despachos y de transformación quedan vinculadas al saldo concreto de Bodega Central;
- los movimientos de almacén son inmutables; una corrección se registra como movimiento inverso.

## Ubicación física contextual

Para Materiales, cámara y posición pertenecen al saldo del almacén físico.

```text
Saldo Bodega Central
└── cámara y posición opcionales

Saldo Packing
└── sin ubicación física WMS
```

`ubicaciones_actuales` se conserva únicamente como proyección de compatibilidad de Bodega Central para las pantallas y operaciones existentes. No representa los saldos virtuales.

Una entrega parcial conserva la ubicación de Bodega. Una entrega total la libera, aunque el folio permanezca activo en un almacén virtual.

## FIFO por almacén

FIFO se aplica dentro del custodio:

1. fecha de vencimiento;
2. fecha de fabricación;
3. fecha de ingreso;
4. número e identificador de folio.

Las solicitudes existentes reservan exclusivamente Bodega Central. El consumo consulta únicamente el almacén indicado. Una excepción FIFO requiere motivo explícito.

## Movimientos

`movimientos_almacenes_materiales` registra un documento único con doble efecto:

```text
Transferencia
Origen:  -100
Destino: +100
Total empresa: sin cambios
```

Conserva saldos anteriores y resultantes, almacenes, centro de costo, usuario, dispositivo y documento relacionado.

Tipos disponibles:

- entrega;
- transferencia;
- devolución;
- consumo;
- ajuste.

## Compatibilidad con la PDA

`POST /api/materiales/despachos/{despacho}/retirar` mantiene el contrato actual, pero ahora:

1. libera la reserva del saldo concreto;
2. disminuye Bodega Central;
3. aumenta el almacén virtual del destino;
4. reconstruye la proyección global sin disminuirla;
5. libera la ubicación física solo cuando Bodega queda en cero.

## Estados

```text
SUM(saldos) > 0  → folio activo
SUM(saldos) = 0  → folio agotado e inactivo
```

La disponibilidad es contextual. Un folio puede tener disponibilidad cero en Bodega y saldo vigente en Packing.

## API y oficina

```http
GET  /api/materiales/almacenes
GET  /api/materiales/almacenes/movimientos
POST /api/materiales/almacenes/movimientos
```

La oficina `/oficina/materiales/almacenes` separa:

1. existencia en Bodega;
2. existencia en centros de costo;
3. existencia total empresa.

Inicialmente los consumos son registrados por los perfiles autorizados del módulo Materiales, en representación del centro de costo. La delegación futura a responsables de cada centro requerirá permisos y perfiles separados.
