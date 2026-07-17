# Estiba WMS móvil

Cliente nativo para tablets Android, construido con Expo, React Native y TypeScript. Está dedicado exclusivamente al trabajo dentro de cámaras: autenticación del operador y dispositivo, sesiones de estiba, plano, ubicación inicial, movimientos y ejecución física de cargas frigoríficas.

## Requisitos locales

- Node.js 24 (la versión usada por CI).
- npm.
- Expo Go compatible con el SDK indicado en `package.json`, en una tablet Android o un emulador.

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

## Modo demostración explícito

El simulador local sigue disponible, pero debe habilitarse deliberadamente:

```bash
EXPO_PUBLIC_DEMO_MODE=true
EXPO_PUBLIC_API_URL=
```

Después de cambiar el modo, reinicia Metro:

```bash
npm run start:clear
```

Los cambios del modo demo viven únicamente durante esa ejecución y nunca llegan a MySQL.

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
- Demo únicamente por activación explícita durante desarrollo.
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

Todavía no incluye lectura real de códigos de barras, persistencia offline, sincronización diferida ni notificaciones persistentes. La cola de cargas se actualiza manualmente hasta incorporar el polling del PR #31.
