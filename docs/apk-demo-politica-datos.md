# Política de datos de Estiba WMS Demo

## Objetivo

La variante `cl.estiba.wms.demo` debe poder presentarse sin Laravel, sin el computador de la
planta y sin revelar información operacional real. Su base `estiba-wms-demo.db` pertenece
exclusivamente a la APK Demo y permanece en la memoria privada de la tablet.

## Datos permitidos

La precarga incorporada al repositorio solo contiene datos ficticios:

- temporadas demostrativas;
- proveedores anonimizados;
- especies, variedades, calibres y envases;
- materiales y destinos demostrativos;
- túneles y perfiles de impresión ficticios;
- clientes, folios, cámaras y cargas creados específicamente para el escenario Demo.

Todos esos registros pueden complementarse localmente desde la tablet. Los cambios sobreviven
al cierre y reinicio de la aplicación.

## Datos prohibidos

Ninguna precarga ni futura exportación comercial puede incluir:

- usuarios reales, correos, contraseñas, tokens o códigos de dispositivos productivos;
- folios, inventario, ubicaciones, saldos o deudas actuales;
- recepciones, validaciones, inspecciones o procesos de prefrío reales;
- cargas, despachos, retornos, repaletizajes o movimientos reales;
- auditorías o notificaciones provenientes de producción.

Si más adelante se genera un catálogo desde MySQL, debe utilizar una lista positiva de tablas
maestras, anonimizar clientes y proveedores, y escribirse fuera del repositorio. Nunca se debe
copiar la base productiva completa dentro de la APK.

## Reinicios disponibles

### Preparar nueva demo

Restaura el escenario operacional ficticio —folios, cámaras, sesiones, cargas, alertas y
movimientos— conservando clientes y maestros creados en la tablet.

### Restaurar todo

Borra la personalización local y reconstruye tanto los maestros como el escenario operacional
con la precarga segura incluida en la APK.

Desinstalar la APK elimina ambos conjuntos porque Android borra la base privada de la aplicación.
