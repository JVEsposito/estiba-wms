# Confirmación antes de retirar un pallet

## Propósito

El planificador dirige a camareros con alta rotación y poca experiencia previa. Antes de
retirar físicamente un pallet, la tablet exige comparar la etiqueta con la tarea y
dejar registrado quién lo hizo. Las posiciones de cámara no tienen código de barras y la
cámara de la Lenovo Tab M11 es lenta para escanear, por lo que la confirmación se digita.

## Flujo en la tablet

1. El camarero pulsa **RETIRAR PALLET**.
2. La pantalla muestra el folio con sus últimos dígitos ocultos: deben leerse en la
   etiqueta física, no copiarse desde la pantalla.
3. El camarero digita esos 4 dígitos en el teclado numérico propio. Si no coinciden, la
   tablet lo indica sin llamar al servidor; tras dos intentos ofrece **EL PALLET NO
   CORRESPONDE · REPORTAR**, que abre el flujo existente de **NO COINCIDE**.
4. Con los dígitos correctos, el camarero ingresa su PIN operacional de 4 dígitos.
5. El servidor verifica ambos datos y recién entonces pasa la tarea a `en_proceso`.

Si el folio tiene menos de 4 dígitos se piden todos. Si en el futuro se incorpora un lector
Bluetooth, el servidor también acepta el folio completo como confirmación.

## PIN operacional

- Es personal, de 4 dígitos y complementa la sesión; no reemplaza la contraseña.
- Se crea en la tablet la primera vez que se confirma un retiro (se digita dos veces).
- Se rechazan PIN predecibles: dígitos repetidos (`0000`) o consecutivos (`1234`, `4321`).
- Cinco intentos fallidos lo bloquean durante 5 minutos.
- Un administrador puede restablecerlo desde **Accesos → Usuarios → Seleccionar acción →
  Restablecer PIN**. El usuario crea uno nuevo en su próximo retiro.
- El supervisor de frío lo restablece desde **Operación ahora → PIN de operadores**, solo
  para camareros de frío, operadores de Prefrío y validadores; nunca el propio ni el de
  otro supervisor o administrador. Se registra quién lo restableció
  (`users.pin_operacional_restablecido_por_user_id` y el log del servidor).
- Se guarda solo como hash y nunca se expone en las respuestas.

## Contrato

```http
GET  /api/usuario/pin
PUT  /api/usuario/pin                      {pin, pin_confirmation, pin_actual?}
POST /api/tareas-movimiento/{id}/iniciar   {confirmacion_folio, pin}
POST /api/administracion/usuarios/{id}/restablecer-pin   (administrador o supervisor de frío)
GET  /api/operacion/pines-operadores                     (operadores de frío y estado del PIN)
```

Un rechazo de folio o PIN responde `422` con el error en `confirmacion_folio` o `pin`.

## Evidencia

La tabla `confirmaciones_inicio_tarea` registra cada intento que llega al servidor con
tarea, folio, usuario, dispositivo, fecha y resultado:

| Resultado | Significado |
|---|---|
| `confirmada` | Dígitos y PIN correctos; la tarea quedó `en_proceso`. |
| `rechazada_folio` | Los dígitos no corresponden al folio de la tarea. |
| `rechazada_pin` | Dígitos correctos, PIN incorrecto, bloqueado o sin crear. |

## Rollback operacional

```dotenv
WMS_CONFIRMACION_INICIO_TAREA=false
```

Con `false` el servidor vuelve a iniciar tareas sin exigir folio ni PIN. La versión de la
tablet que envía la confirmación sigue funcionando porque el servidor ignora esos campos.

## Despliegue

Una tablet con una versión anterior no envía `confirmacion_folio` ni `pin`, y el servidor
rechazaría el inicio con `422`. Para desplegar sin cortar la operación:

1. publicar el servidor con `WMS_CONFIRMACION_INICIO_TAREA=false`;
2. publicar la actualización de la aplicación (EAS) y verificar que las tablets la tomaron;
3. cambiar a `WMS_CONFIRMACION_INICIO_TAREA=true` y limpiar la caché de configuración.
