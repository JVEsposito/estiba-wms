# Cierre de temporada

La fruta nunca cruza de una temporada a otra. Si al terminar una temporada
queda un pallet en una cámara, un lote abierto o una carga sin cerrar, se debe
a que el registro no se cerró, no a que la fruta siga ahí.

Antes de activar la temporada siguiente, esos registros se cierran en su módulo
o se regularizan con una baja administrativa auditada. Mientras queden, la
activación se bloquea.

Materiales no participa: su inventario cruza de temporada con la migración
auditada.

## Qué se revisa

Todas las categorías excepto sesiones de estiba se revisan en la temporada
diagnosticada. Las sesiones de estiba pertenecen a una cámara y no a una
temporada, así que se revisan solo al diagnosticar la temporada activa.

| Categoría (`categoria`) | Pendiente cuando | Cierre normal |
| --- | --- | --- |
| Recepciones de Romana (`recepciones_romana`) | El estado no es `cerrado` | Registrar la salida del camión |
| Validación MP (`validacion_mp`) | La recepción no está `validada`: sin validar o tomada sin confirmar | Confirmar la validación |
| Lotes MP (`lotes_mp`) | El estado no es `entregado_proceso` ni `anulado` | Entregar a proceso o anular |
| Hidrocooler (`hidrocooler`) | El proceso está `en_curso` | Registrar el término |
| Prefrío (`prefrio`) | El proceso está activo, de borrador a reproceso | Finalizar o cancelar |
| Inspección SAG (`inspeccion_sag`) | El lote no está finalizado ni cancelado | Registrar resultado o cancelar |
| Embarques (`embarques`) | Tentativo, o confirmado sin carga | Confirmar con su carga o cancelar |
| Cargas (`cargas`) | La carga no está cerrada ni cancelada | Registrar la salida o cancelar |
| Planes de estiba (`planes_operacionales`) | Programado, en ejecución o pausado | Completar o cancelar |
| Sesiones de estiba (`sesiones_estiba`) | La sesión está `abierta` en una cámara que no es de Materiales | Cerrarla o forzar el cierre |
| Folios PT (`folios_pt`) | Un pallet o saldo que no está en estado terminal, o que sigue ubicado en una cámara | Despachar, anular la validación o consolidar en una repa |

Cada registro pendiente muestra:

- su referencia y un detalle;
- su responsable, cuando se conoce: quien lo creó, lo tomó o lo inició; en los
  folios, quien lo movió por última vez;
- su fecha y, si es un folio, su ubicación.

## Regularización

Se hace en **Accesos → Temporadas → Cierre** o con
`POST /api/administracion/temporadas/{temporada}/cierre/regularizar`:

```json
{
  "categoria": "folios_pt",
  "ids": ["..."],
  "motivo_categoria": "despachado_sin_registro",
  "motivo": "Pallets despachados en marzo sin cerrar la carga; confirmado con guías."
}
```

- **Categoría del motivo, obligatoria:**
  - `despachado_sin_registro`;
  - `merma_anulacion`;
  - `error_digitacion`;
  - `dato_prueba`.
- **Motivo:** de 10 a 500 caracteres.
- **Límite:** 500 registros por solicitud. Todos deben seguir pendientes; si
  alguno ya no lo está, no se regulariza ninguno.
- **Registro:** cada uno queda en `regularizaciones_cierre_temporada`, que no
  admite modificación ni eliminación. Guarda:
  - el estado anterior;
  - una fotografía del registro, con la ubicación de los folios;
  - el motivo;
  - el usuario y la fecha.
- **Efecto:**
  - **Folio PT:** pasa a `retirado_definitivo` y queda inactivo. Libera su
    posición y su reserva de carga sobrante, y la cámara sube su versión de
    plano.
  - **Sesión de estiba:** se cierra con cierre forzado y libera la cámara.
  - **Prefrío:** cancela el proceso mediante su evento operacional, libera el
    túnel y retiene los folios si el ciclo había comenzado. Esos folios siguen
    pendientes y deben resolverse después.
  - **Hidrocooler:** cancela el ciclo y libera la clave del equipo, sin registrar
    mediciones finales ficticias. El lote vuelve a `pendiente_hidrocooler` y
    sigue pendiente de resolución por separado. Un lote que ya tuvo un ciclo no
    puede iniciar otro bajo el contrato actual; se cierra en Materia Prima o
    se regulariza antes de activar la temporada siguiente.
  - **El resto:** conserva su estado y deja de contar como pendiente. No se
    admite regularizar una carga con folios, tareas o un camión en andén; un
    plan con tareas de movimiento; ni un lote MP asignado a cámara o con entrega
    parcial. Hay que resolver primero esos vínculos en sus módulos.
  - **SAG:** se finaliza o cancela desde Inspección SAG; su regularización
    administrativa está deshabilitada para que libere sus reservas.
- **Orden:** los folios PT se regularizan al final. Antes deben quedar
  cerrados o regularizados el prefrío, las inspecciones SAG, las cargas y los
  planes de esa temporada, porque cualquiera de ellos puede reservar un folio.
- **Permiso:** `administrar-accesos`.

## Bloqueo de la activación

Estas acciones responden **409** con `codigo: temporada_con_pendientes` y la
lista de pendientes por temporada y categoría:

- activar;
- crear o editar una temporada marcándola activa;
- migrar con `activar_destino`.

Bloquean la activación:

1. cualquier pendiente de la temporada vigente;
2. folios PT de cualquier temporada distinta de la vigente que todavía
   figuren en una cámara, incluso si pertenecen a la temporada que se desea
   activar. Si son registros de prueba, se regularizan con `dato_prueba` desde
   el cierre de su temporada.

La oficina abre el cierre de la temporada afectada cuando recibe ese error.

## Registros sin cerrar en el plano

En el plano de la cámara (`GET /api/camaras/{camara}/plano`):

- cada folio PT de una temporada distinta de la activa trae
  `registro_sin_cerrar` con la temporada a la que pertenece;
- la cámara informa `registros_sin_cerrar`.

La tablet muestra esas posiciones con borde punteado ámbar y la marca «SIN
CERRAR · código». La oficina de Cámaras muestra la cantidad junto a la versión
del plano. El ETag del plano incluye la temporada activa.

## Aviso a responsables

**Avisar a responsables**
(`POST /api/administracion/temporadas/{temporada}/cierre/avisar`) crea una
notificación operacional (`cierre_temporada_pendiente`) para cada usuario
activo que tenga registros pendientes. La notificación trae:

- las cantidades por categoría;
- hasta 10 referencias por categoría;
- cómo cerrar cada una.

El aviso no se repite mientras la lista de pendientes del usuario no cambie.

El aviso aparece en:

- la franja superior de todas las oficinas, hasta que el usuario pulsa
  «Entendido»;
- el centro de notificaciones de la tablet.

Los registros sin responsable identificado se informan como `sin_responsable`.

## Comando

```bash
php artisan temporadas:diagnosticar            # temporada activa
php artisan temporadas:diagnosticar T26 --detalle
php artisan temporadas:diagnosticar T26 --json
```

- **Solo lectura:** no cambia ningún registro.
- **Salida:** termina con código 0 si no quedan pendientes y con 1 si quedan,
  así que sirve como verificación antes de activar.
