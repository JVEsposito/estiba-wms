# Interfaz móvil de control ambiental

## Alcance

Esta entrega consume el contrato de `CONTROL_AMBIENTAL_HORARIO.md` desde el módulo
Operación frigorífico. Agrega la vista **Ambiente** para tablet y PDA, sin cambiar
persistencia, permisos ni reglas del backend.

La interfaz presenta primero las cámaras vencidas, luego las pendientes y finalmente
las vigentes. Cada tarjeta conserva el código completo, el estado textual y una señal
visual, la hora de vigencia y las últimas lecturas disponibles. Un resumen muestra
cuántas cámaras requieren control y cuántas siguen vigentes.

Mientras el usuario permanece en Labores o Plano y operación, el contenedor consulta
el estado ambiental cada 60 segundos. Si existe al menos una cámara pendiente o
vencida, muestra un aviso visible que abre Ambiente. La propia vista también actualiza
su estado cada 60 segundos y permite actualización manual.

## Captura segura

El camarero selecciona una cámara que requiere control e ingresa temperatura de
inicio, medio y fondo. La interfaz acepta punto o coma y exige como máximo dos
decimales, de acuerdo con el contrato API. Antes de enviar muestra las tres lecturas,
el camarero y el equipo para confirmación explícita.

Al confirmar se generan una hora de captura y un `operacion_id`. El payload exacto
se guarda en el equipo antes de enviarlo. Si la conexión falla o la respuesta se
pierde, los campos quedan bloqueados y el usuario puede reintentar el mismo payload;
la idempotencia del backend impide duplicarlo. El usuario también puede actualizar
el estado y descartar el intento guardado de forma explícita.

Los borradores se separan por usuario y dispositivo. Una captura confirmada elimina
el borrador y actualiza las cámaras inmediatamente.

## Acceso y presentación

- La pestaña aparece solo si la sesión informa
  `puede_consultar_control_ambiental`.
- El formulario aparece solo con `puede_registrar_control_ambiental`.
- Los perfiles de consulta ven estado y lecturas, sin controles de escritura.
- Los controles táctiles miden al menos 56 px y la composición cambia a una columna
  bajo 840 px.
- La pantalla usa los tokens y componentes corporativos Estiba incorporados en el
  PR 266.

## Siguiente entrega visual

La corrección auditada y el historial corresponden a una vista web de supervisión.
Consumirá `GET /api/control-ambiental/registros` y
`PUT /api/control-ambiental/registros/{registro}/corregir`, conservando el contrato
backend existente.
