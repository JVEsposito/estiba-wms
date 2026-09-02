# Demo comercial web local

## Propósito

La oficina `/oficina/demo` permite presentar Estiba WMS a gerencia, compradores e
inversionistas sin exponer información de planta ni modificar la temporada productiva.
El escenario contiene datos ficticios de Romana, Materia Prima, Hidrocooler, Frigorífico,
Materiales y Trazabilidad.

## Habilitación

1. El usuario abre **Administración → Demo comercial**.
2. Laravel valida la sesión Sanctum y autoriza únicamente el rol base `administrador`.
3. El navegador crea el escenario ficticio en `sessionStorage`.
4. Desde ese momento la oficina demo trabaja solo con ese conjunto local.

El endpoint `GET /api/demo/autorizar` no crea una temporada, no activa banderas y no guarda
estado demo. Su única función es verificar nuevamente el rol del usuario antes de permitir que
el navegador prepare el escenario.

## Aislamiento

- La sesión y los datos demo existen solo en la pestaña que los habilitó.
- Recargar esa pestaña conserva el recorrido.
- Cerrar la pestaña, pulsar **Salir de demo** o cerrar la sesión elimina el escenario.
- Otra pestaña, navegador o computador no recibe el modo demo.
- La oficina demo no consulta endpoints de temporadas, cámaras, inventario ni procesos reales.
- Los códigos, clientes, folios, kilos, posiciones y movimientos precargados son ficticios.
- El token normal de oficina sigue almacenándose con la política vigente; la condición demo y
  su población nunca se guardan en `localStorage` ni en MySQL.

## Operación permitida

La presentación permite navegar por los dominios, buscar expedientes ficticios, restablecer la
población y simular un corte posterior. Esas acciones solo mutan el objeto almacenado en
`sessionStorage`; no emiten comandos operacionales al servidor.

## Diferencia con la APK Demo

La APK `cl.estiba.wms.demo` es una aplicación autónoma con SQLite y puede conservar su escenario
entre reinicios. La demo web es deliberadamente efímera y se elimina al terminar la pestaña para
evitar que un PC compartido permanezca accidentalmente en modo presentación.
