# Estiba WMS móvil

Cliente nativo para tablets Android, construido con Expo, React Native y TypeScript. Replica el flujo operacional conectado publicado por Laravel: autenticación del operador y dispositivo, cámaras, sesiones de estiba, plano, ubicación inicial y movimientos.

## Requisitos locales

- Node.js 24 (la versión usada por CI).
- npm.
- Expo Go compatible con el SDK indicado en `package.json`, en una tablet Android o un emulador.

PHP, Composer y MySQL no son necesarios para trabajar únicamente en esta carpeta. Serán necesarios cuando se quiera ejecutar el backend Laravel en el mismo equipo.

## Primer inicio en modo demostración

Si no existe un archivo `.env`, la app usa datos locales interactivos. No requiere levantar Laravel y permite probar el login, abrir una estiba, ubicar un folio y moverlo.

```bash
cd mobile
npm ci
npm run start:clear
```

Las credenciales vienen cargadas en el formulario. La aplicación está fijada en orientación horizontal porque el flujo está diseñado para tablets.

## Conectar con Laravel

1. Copiar `.env.example` como `.env`.
2. Reemplazar `192.168.1.100` por la IPv4 del computador en la red local. Desde el teléfono, `localhost` apuntaría al propio teléfono.
3. En la raíz del repositorio, iniciar Laravel para que escuche en la red:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

4. En otra terminal, iniciar Expo:

```bash
cd mobile
npm run start:clear
```

El computador y la tablet deben estar en la misma red y el firewall debe permitir Node.js y PHP en la red privada. Si la red bloquea la conexión LAN, se puede intentar `npm run start:tunnel`.

## Validaciones

```bash
npm run typecheck
npx expo-doctor@latest
npm run export:android
```

GitHub Actions ejecuta ambas validaciones en cada pull request.

## Alcance actual

- Login por email, contraseña y código del dispositivo.
- Modo demo automático y modo conectado mediante `EXPO_PUBLIC_API_URL`.
- Selector de cámaras, ocupación, bloqueo y modo de solo lectura.
- Apertura y cierre de sesiones de estiba.
- Ubicación inicial con datos del folio y condición SAG.
- Reubicación dentro de una cámara y traslado entre cámaras.
- Historial de movimientos recientes.

Todavía no incluye lectura real de códigos de barras, persistencia offline ni sincronización diferida.
