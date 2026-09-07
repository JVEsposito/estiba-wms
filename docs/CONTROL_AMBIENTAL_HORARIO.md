# Control ambiental horario

## Alcance

El backend registra el control manual de temperatura que realiza el camarero en
cámaras activas de producto terminado. Cada control contiene tres lecturas:

- inicio de la cámara;
- punto medio;
- fondo de la cámara.

La frecuencia inicial es de 60 minutos. Humedad y temperatura de pulpa no forman
parte de este contrato: la evidencia operacional disponible no define humedad y
la pulpa corresponde a un control distinto.

No se incorporan pantallas web, tablet o PDA en esta entrega.

## Reglas

- El registro exige un usuario autorizado y un token asociado a una tablet activa.
- Se conservan cámara, usuario, dispositivo, hora capturada y hora recibida por el
  servidor.
- `operacion_id` hace idempotente el reintento del mismo payload.
- Una cámara no admite dos controles cuyas horas capturadas estén separadas por
  menos de 60 minutos. El límite exacto de 60 minutos sí inicia una nueva vigencia.
- Cada control permanece vigente desde `capturado_at` hasta antes de completar los
  60 minutos.
- Solo un supervisor de frío o administrador puede corregir temperaturas.
- La corrección exige la `version` leída, un UUID idempotente y un motivo. Usuario,
  dispositivo, cámara y hora original permanecen inmutables.
- Cada corrección conserva valores anteriores, valores nuevos, responsable, motivo
  y fecha. Los registros y sus correcciones no admiten eliminación física.

## Contrato API

Todas las rutas requieren autenticación Sanctum.

| Método | Ruta | Uso |
|---|---|---|
| `GET` | `/api/control-ambiental/estado` | Vigencia de las cámaras activas de PT |
| `GET` | `/api/control-ambiental/registros` | Historial paginado y auditable |
| `POST` | `/api/control-ambiental/camaras/{camara}/registros` | Captura desde tablet |
| `PUT` | `/api/control-ambiental/registros/{registro}/corregir` | Corrección supervisora |

`GET /estado` acepta `camara_id`. Cada cámara informa uno de estos estados:

- `pendiente`: aún no posee controles;
- `vigente`: el último control no ha cumplido 60 minutos;
- `vencido`: requiere un nuevo control.

`GET /registros` acepta `camara_id`, `desde`, `hasta` y `per_page`.

### Registrar

```json
{
  "operacion_id": "85f0232f-d07c-4e7f-a2ce-e7750f914d84",
  "temperatura_inicio_c": -0.8,
  "temperatura_medio_c": -0.6,
  "temperatura_fondo_c": -0.7,
  "capturado_at": "2026-09-07T15:45:00-03:00"
}
```

El primer procesamiento responde `201`; un reintento idéntico responde `200`. Un
UUID reutilizado con datos diferentes o un segundo control dentro de la ventana
responde `409` con `codigo=conflicto_operacional`.

### Corregir

```json
{
  "operacion_id": "7372127e-befc-4a63-ae75-4de73dd0c67b",
  "version": 0,
  "temperatura_inicio_c": -0.9,
  "temperatura_medio_c": -0.6,
  "temperatura_fondo_c": -0.7,
  "motivo": "La lectura inicial se transcribió incorrectamente."
}
```

Si la versión ya cambió, la API responde `409` para impedir que dos correcciones
se sobrescriban silenciosamente.
