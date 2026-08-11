# Estiba WMS móvil

Cliente nativo para tablets Android, construido con Expo, React Native y TypeScript. Reúne la operación física de cámaras, cargas, recepción de materiales e impresión de etiquetas desde terreno.

## Requisitos locales

- Node.js 24 (la versión usada por CI).
- npm.
- Expo Go compatible con el SDK indicado en `package.json`, en una tablet Android o un emulador, salvo al probar funciones nativas como la impresión por IP.

PHP, Composer y MySQL no son necesarios para trabajar únicamente en esta carpeta. Serán necesarios cuando se quiera ejecutar el backend Laravel en el mismo equipo.

## Servidor Laravel configurable desde la tablet

La APK no queda amarrada a una IP. En la pantalla de acceso, pulsa **Configurar servidor** e ingresa la dirección del computador que ejecuta Laravel:

```text
10.16.104.25:8000
```

También acepta una URL completa, por ejemplo `https://wms.empresa.cl`. Si no se escribe el protocolo, la aplicación utiliza `http://`. La dirección normalizada queda almacenada en la tablet y se conserva al cerrarla o reiniciarla.

`EXPO_PUBLIC_API_URL` continúa disponible como valor inicial opcional para desarrollo, pero ya no es obligatorio para la APK.

1. Desde la raíz, inicia Laravel en la red local:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

2. Para desarrollo, inicia Expo:

```bash
cd mobile
npm ci
npm run start:clear
```

3. Abre la aplicación en Expo Go, configura el servidor y verifica que el encabezado diga **API conectada**, no **Demo local**.

Desde la tablet, `localhost` apuntaría a la propia tablet. El computador y la tablet deben estar en la misma red y el firewall debe permitir Node.js y PHP en la red privada.

La guía completa para preparar MySQL, ejecutar un movimiento y comprobarlo directamente en la base está en [`docs/prueba-escritura-mysql.md`](../docs/prueba-escritura-mysql.md).

## APK instalable y actualizaciones automáticas

La aplicación utiliza EAS Build para generar una APK firmada de distribución interna y EAS Update para actualizar automáticamente JavaScript, estilos e imágenes. La APK comprueba actualizaciones al arrancar y, si encuentra una compatible, la descarga y reinicia usando la última versión válida como respaldo.

Vinculación inicial —se realiza una sola vez con la cuenta Expo propietaria del proyecto—:

```bash
cd mobile
npx eas-cli@latest login
npm run eas:configure
```

Ese comando agrega a `app.json` el `projectId` y la URL segura de EAS Update. Después se genera la APK:

```bash
npm run build:apk
```

Para publicar cambios de interfaz o lógica que no agreguen dependencias nativas:

```bash
npm run update:production -- --message "Descripción del cambio"
```

Cambios nativos —por ejemplo instalar otra biblioteca nativa, modificar permisos o subir la versión de Expo— requieren incrementar la versión, generar una APK nueva e instalarla. EAS Update no reemplaza silenciosamente el binario Android.

La impresión directa de etiquetas utiliza un módulo Android local que abre una conexión RAW TCP con la impresora configurada (puerto `9100` por defecto). Por eso debe probarse con la APK `1.2.0` o superior: Expo Go y una APK anterior muestran la interfaz, pero no pueden abrir el socket de impresión.

## Modo demostración explícito

El simulador local de desarrollo sigue disponible, pero debe habilitarse deliberadamente:

```bash
EXPO_PUBLIC_DEMO_MODE=true
EXPO_PUBLIC_API_URL=
```

Después de cambiar el modo, reinicia Metro:

```bash
npm run start:clear
```

Los clientes y folios creados en el modo demo quedan almacenados en SQLite dentro del dispositivo y nunca llegan a MySQL.

## APK autónoma para demostraciones

La variante **Estiba WMS Demo** utiliza el paquete Android `cl.estiba.wms.demo`, por lo que puede instalarse junto a la APK productiva. No permite configurar una URL de Laravel, deshabilita EAS Update y guarda sus datos exclusivamente en `estiba-wms-demo.db` dentro de la memoria privada de la tablet.

Incluye un escenario ficticio inicial y una administración local desde la que se pueden:

- crear y eliminar clientes;
- crear y eliminar folios con especie, variedad y cajas;
- conservar los cambios después de cerrar o reiniciar la app;
- restaurar el escenario inicial antes de otra presentación.

El motor operativo local también permite:

- abrir y cerrar sesiones de estiba sin Laravel;
- buscar en cámaras los folios creados en Administración Demo;
- ubicar folios en posiciones libres;
- reubicar dentro de una cámara o trasladar entre cámaras;
- conservar planos, sesiones e historial de movimientos después de reiniciar la app;
- rechazar operaciones con una versión antigua del plano;
- aceptar reintentos idénticos sin duplicar movimientos y rechazar UUID reutilizados con otro contenido.

El botón **Restaurar escenario** reinicia conjuntamente maestros, folios, cámaras, posiciones, sesiones, movimientos y auditoría local.

Credenciales sugeridas —el modo local sólo exige que los tres campos estén completos—:

```text
Correo: administrador@estiba.demo
Contraseña: password
Tablet: DEMO-01
```

Para ejecutar la variante en desarrollo:

```bash
cd mobile
npm ci
npm run start:demo
```

Para validar el bundle autónomo o solicitar una APK interna a EAS:

```bash
npm run export:demo
npm run build:apk:demo
```

`expo-sqlite` es una dependencia nativa. La primera instalación de esta variante requiere generar una APK nueva; no puede agregarse a una APK anterior por OTA. Al desinstalar **Estiba WMS Demo**, Android también elimina su base local.

Prueba manual mínima del motor local:

1. Crea un cliente y un folio desde **Administración Demo**.
2. Cambia a **Operación frigorífico** y abre `CAM-01`.
3. Selecciona una posición libre, busca el folio creado y confirma su ubicación.
4. Muévelo a otra posición y comprueba el historial.
5. Cierra por completo la aplicación, vuelve a entrar y confirma que la ubicación y el historial permanecen.
6. Regresa a **Administración Demo** y utiliza **Restaurar escenario** para recuperar los datos iniciales.

## Validaciones

```bash
npm run typecheck
npx expo-doctor@latest
npm run export:android
```

GitHub Actions ejecuta ambas validaciones en cada pull request.

## Alcance actual

- Login por email, contraseña y código del dispositivo.
- Dirección de Laravel configurable y persistente en cada tablet.
- APK independiente con perfil de distribución interna.
- Actualización OTA automática para interfaz y lógica compatible.
- APK Demo separada, sin servidor externo, con clientes y folios persistentes en SQLite.
- Confirmación visual después de guardar una ubicación o movimiento en el servidor.
- Errores operacionales visibles dentro de los modales.
- Actualización automática del plano cada 30 segundos y al volver a la aplicación.
- Selector de cámaras, ocupación, bloqueo y modo de solo lectura.
- Apertura y cierre de sesiones de estiba.
- Ubicación inicial con datos del folio y condición SAG.
- Reubicación dentro de una cámara y traslado entre cámaras.
- Historial de movimientos recientes.
- Bandeja compartida de cargas de frío con prioridad, incidencias y concentración.
- Ruta de extracción calculada desde la entrada hacia el fondo sobre el plano vertical.
- Reporte de incidencias físicas desde terreno.
- Envío individual o secuencial de folios a un andén.
- Centro de notificaciones persistentes con lectura y confirmación individual.
- Polling resiliente cada 12 segundos para alertas, cargas y rutas de extracción.
- Recepción de materiales con folios trazables.
- Selección de uno, varios o todos los folios de una recepción confirmada.
- Perfiles ZPL por fabricante, modelo, formato y DPI para equipos Zebra y Bixolon compatibles.
- Impresora por IP configurable y persistente por dispositivo, con prueba de conexión y resultado auditable.

La APK conectada todavía no incluye persistencia offline general ni sincronización diferida. Durante una interrupción conserva el último estado descargado, pero las operaciones nuevas continúan requiriendo conexión con Laravel. La persistencia SQLite descrita arriba corresponde exclusivamente a la variante Demo y se ampliará módulo por módulo. Un envío de impresión que pierde confirmación después de comenzar se marca como indeterminado y nunca se reintenta automáticamente.
