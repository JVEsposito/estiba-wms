# Custodia distribuida de materiales

## Regla de negocio

La entrega de Bodega a un centro de costo es una **transferencia interna**. No reduce la existencia total del folio ni lo deja inactivo.

La existencia total se calcula como:

```text
Existencia empresa = suma de saldos de todos los almacenes
```

Solo las operaciones de **consumo** y **ajuste negativo** disminuyen la existencia total.

## Modelo

El catálogo histórico `destinos_materiales` evoluciona como catálogo de almacenes:

- `fisica`: Bodega Central de Materiales, con cámara y posición opcional;
- `virtual`: Packing, Frigorífico, Mantención, Calidad u otro custodio lógico.

El centro de costo se mantiene como atributo contable del almacén. No reemplaza su identidad operacional.

### Saldos por almacén

`saldos_materiales_almacenes` conserva por folio:

- almacén custodio;
- cantidad actual;
- cantidad reservada;
- cámara y posición, solamente cuando corresponde a una bodega física.

`folios_materiales.cantidad_actual` se mantiene como total empresa para compatibilidad con Recepción, Transformación, bloqueos e integraciones existentes.

### Kardex distribuido

`movimientos_almacenes_materiales` registra:

- entrega;
- transferencia;
- devolución;
- consumo;
- ajuste.

Cada asiento conserva origen, destino, saldos anteriores y resultantes, centro de costo, actor, dispositivo y documento relacionado.

## Comportamiento de los despachos existentes

`POST /api/materiales/despachos/{despacho}/retirar` conserva su contrato para la PDA, pero cambia su efecto:

1. libera la reserva FIFO de Bodega;
2. disminuye el saldo de Bodega Central;
3. aumenta el saldo del almacén virtual asociado al destino;
4. no modifica la cantidad total del folio;
5. elimina la ubicación física solamente cuando Bodega queda en cero;
6. mantiene el folio activo mientras exista saldo en cualquier almacén.

## Operaciones nuevas

### Consulta

```http
GET /api/materiales/almacenes
GET /api/materiales/almacenes/movimientos
```

### Movimiento

```http
POST /api/materiales/almacenes/movimientos
```

Ejemplo de consumo:

```json
{
  "operacion_id": "uuid",
  "tipo": "consumo",
  "folio_id": "uuid",
  "almacen_origen_id": "uuid",
  "cantidad": 20,
  "motivo": "Producción turno noche",
  "documento_relacionado": "OT-2026-001"
}
```

Ejemplo de devolución:

```json
{
  "operacion_id": "uuid",
  "tipo": "devolucion",
  "folio_id": "uuid",
  "almacen_origen_id": "uuid-packing",
  "almacen_destino_id": "uuid-bodega",
  "cantidad": 12,
  "motivo": "Sobrante de producción",
  "camara_destino_id": "uuid-camara",
  "posicion_destino_id": "uuid-posicion-opcional"
}
```

Los movimientos son idempotentes por `operacion_id` y rechazan reutilizaciones con un payload diferente.

## FIFO

- Las solicitudes de Bodega reservan únicamente saldos de la Bodega Central.
- El consumo aplica FIFO dentro del almacén custodio.
- Una excepción FIFO exige una justificación explícita.
- Un centro de costo no puede consumir saldo perteneciente a otro almacén.

## Estados

- saldo total mayor que cero: folio activo;
- saldo total igual a cero: folio `agotado` e inactivo;
- saldo cero en Bodega con existencia virtual: folio activo y sin ubicación física.

## Oficina

La vista `/oficina/materiales/almacenes` separa:

1. existencia en Bodega;
2. existencia en centros de costo;
3. existencia total empresa.

También permite registrar consumo, devolución, transferencia y ajuste, y muestra el kardex distribuido.

## Puesta en marcha

Después de fusionar:

```bash
git pull
composer install
php artisan migrate
php artisan optimize:clear
npm ci
npm run build
php artisan test --filter=CustodiaDistribuidaMaterialesTest
```

No se requiere actualizar la APK para conservar el flujo actual de entrega; el endpoint existente mantiene su contrato. La nueva oficina sí requiere reconstruir los recursos web.
