# Módulo de validación de pallets

## Objetivo

Registrar el nacimiento trazable de pallets de producto antes de prefrío.

```text
Recepción física
→ Validación
→ Pendiente de prefrío
→ Prefrío
→ Ubicación
→ Disponible para carga
```

Una aprobación crea el folio, pero no lo deja disponible para cargas. El estado inicial es `pendiente_prefrio`.

## Catálogos

La fundación incorpora:

- temporadas con versión de catálogo;
- combinaciones activas de especie, variedad, calibre y envase;
- combinaciones comerciales de cliente, marca, CSG y predio.

La versión permite que una PDA informe con qué catálogo trabajó. Una diferencia queda visible como catálogo desactualizado, sin destruir una operación capturada offline.

## Intentos e idempotencia

Cada envío usa `operacion_id` UUID y hash del payload.

- repetir el mismo UUID con el mismo contenido devuelve la validación existente;
- reutilizarlo con contenido diferente genera conflicto;
- cada folio conserva intentos numerados;
- observar o rechazar nunca crea inventario;
- una aprobación posterior a una observación genera un nuevo intento;
- una segunda aprobación del mismo folio se registra como conflicto y no duplica el folio.

La tabla `secuencias_validacion_folio` serializa solamente los intentos del mismo folio, por lo que dos PDAs pueden validar pallets distintos en paralelo.

## Roles

### Validador

Puede descargar catálogos, aprobar, observar y consultar validaciones. No ve ni opera cámaras, cargas o materiales.

### Supervisor de frío

Puede realizar las mismas acciones y confirmar un rechazo definitivo.

### Administrador

Conserva acceso total.

## API

```http
GET /api/validacion/catalogos
GET /api/validacion/pallets?folio=PAL-0001
GET /api/validacion/pallets/{id}
POST /api/validacion/pallets
```

Payload base:

```json
{
  "operacion_id": "uuid",
  "numero_folio": "10293847",
  "tipo_bulto": "pallet",
  "cantidad_cajas": 120,
  "temporada_id": "uuid",
  "catalogo_version": 1,
  "articulo_validacion_id": "uuid",
  "origen_validacion_id": "uuid",
  "resultado": "aprobado",
  "motivo": null,
  "observacion": null,
  "generado_dispositivo_at": "2026-07-17T11:42:00-04:00"
}
```

## Próximas entregas

Esta PR no incorpora aún:

- administración web de catálogos;
- importador Excel con previsualización;
- interfaz PDA;
- SQLite y bandeja offline;
- fotografías de observaciones;
- flujo de prefrío que promueve el folio a disponible;
- integración con Suit Export.
