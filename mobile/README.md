# Estiba WMS móvil

Cliente nativo para tablets Android, construido con Expo, React Native y TypeScript. Replica el flujo operacional conectado publicado por Laravel: autenticación del operador y dispositivo, cámaras, sesiones de estiba, plano, ubicación inicial y movimientos.

## Requisitos locales

- Node.js 24 (la versión usada por CI).
- npm.
- Expo Go compatible con el SDK indicado en `package.json`, en una tablet Android o un emulador.

PHP, Composer y MySQL no son necesarios para trabajar únicamente en esta carpeta. Serán necesarios cuando se quiera ejecutar el backend Laravel en el mismo equipo.

## Modo conectado con Laravel y MySQL

El modo conectado es el comportamiento esperado para la prueba operacional. Copia `.env.example` como `.env`, conserva `EXPO_PUBLIC_DEMO_MODE=false` y reemplaza la IP por la IPv4 del computador que ejecuta Laravel:

```bash
EXPO_PUBLIC_DEMO_MODE=false
EXPO_PUBLIC_API_URL=http://192.168.1.100:8000
```

Sin una URL válida, la aplicación muestra **API no configurada** y bloquea el acceso. Ya no entra silenciosamente en demostración.

1. Desde la raíz, inicia Laravel en la red local:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

2. Inicia Expo limpiando su caché para que lea el nuevo `.env`:

```bash
cd mobile
npm ci
npm run start:clear
```

3. Abre la aplicación en Expo Go. Antes de operar, verifica que el encabezado diga **API conectada**, no **Demo local**.

Desde la tablet, `localhost` apuntaría a la propia tablet. El computador y la tablet deben estar en la misma red y el firewall debe permitir Node.js y PHP en la red privada.

La guía completa para preparar MySQL, ejecutar un movimiento y comprobarlo directamente en la base está en [`docs/prueba-escritura-mysql.md`](../docs/prueba-escritura-mysql.md).

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
- Modo conectado seguro mediante `EXPO_PUBLIC_API_URL` y demo únicamente por activación explícita.
- Confirmación visual después de guardar una ubicación o movimiento en el servidor.
- Errores operacionales visibles dentro de los modales.
- Actualización automática del plano cada 30 segundos y al volver a la aplicación.
- Selector de cámaras, ocupación, bloqueo y modo de solo lectura.
- Apertura y cierre de sesiones de estiba.
- Ubicación inicial con datos del folio y condición SAG.
- Reubicación dentro de una cámara y traslado entre cámaras.
- Historial de movimientos recientes.

Todavía no incluye lectura real de códigos de barras, persistencia offline ni sincronización diferida.
