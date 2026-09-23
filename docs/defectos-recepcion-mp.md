# Defectos de recepción MP: contrato inicial

Este registro documenta daños observados por el validador MP y no cambia las cantidades
validadas ni los motivos de segregación. Se asocia al ID de recepción de Romana, no solo
al número de guía: distintas recepciones pueden tener números de guía parecidos.

## Captura desde la tablet

El usuario debe tener el módulo `validacion_mp`, usar un token de tablet registrada y
haber tomado la recepción de la temporada activa. Puede agregar más de un defecto por
recepción, incluso después de confirmar la validación. Las operaciones son inmutables.

`POST /api/validacion-mp/recepciones/{recepcion}/defectos` acepta `multipart/form-data`:

| Campo | Tipo | Requisito |
| --- | --- | --- |
| `operacion_id` | UUID | Obligatorio; conservarlo en cada reintento del mismo envío. |
| `categoria` | Texto | `envase_danado`, `envase_sucio`, `producto_danado` u `otro`. |
| `tipo_envase` | Texto | Opcional: `bins`, `totes` o `esponjas`. |
| `cantidad_afectada` | Entero | Opcional, entre 1 y 100000. |
| `descripcion` | Texto | Obligatorio, máximo 2000 caracteres. |
| `fotografias[]` | Archivo | Entre 1 y 3 imágenes JPEG, PNG o WebP; hasta 1536 KiB cada una. |
| `fotografia_guia` | Archivo | Imagen opcional con los mismos formatos y límite. |

El número de guía, cliente, temporada, validador, dispositivo y hora se toman del
servidor. El mismo UUID y contenido devuelve el registro existente (200); un UUID
reutilizado para otro contenido devuelve 409. Un registro nuevo devuelve 201.

`GET /api/validacion-mp/recepciones/{recepcion}/defectos` devuelve los registros de
la recepción al validador asignado, solo durante su temporada activa. Las rutas de
captura responden con una lista `evidencias` que contiene identificadores, tipo y URL
privada; nunca incluyen rutas internas del servidor.

## Consulta para auditoría

Administrador y supervisor de frío con acceso a Romana o Materia Prima pueden
consultar `GET /api/materia-prima/defectos-recepcion`. Por defecto usa la temporada
activa; admite `temporada_id` explícita, `desde`, `hasta`, `categoria`, `buscar` y
`page`. Devuelve hasta 50 filas por página; el historial de temporadas pasadas sigue
disponible. `GET /api/materia-prima/defectos-recepcion/{defecto}` obtiene el detalle.

Las fotos se leen exclusivamente por
`GET /api/materia-prima/defectos-recepcion/{defecto}/evidencias/{evidencia}`:
el servidor verifica que la foto pertenezca al registro y que el usuario sea auditor
o el validador que la registró en la temporada vigente.

Los archivos se guardan en el disco privado `local` (`storage/app/private`). **Los
respaldos deben incluir ese directorio además de la base de datos**; un volcado SQL
por sí solo no conserva las fotos. La pantalla PDA y las exportaciones de Oficina se
agregarán en los PR siguientes.
