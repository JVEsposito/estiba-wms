# Estiba WMS móvil

Prototipo de la aplicación para tablets Android, construido con Expo y TypeScript. La primera pantalla representa el tablero operativo con datos simulados para poder validar la experiencia antes de publicar el contrato de la API.

## Requisitos locales

- Node.js 24.
- npm.
- Expo Go en una tablet Android o un emulador Android.

PHP, Composer y MySQL no son necesarios para trabajar únicamente en esta carpeta. Serán necesarios cuando se quiera ejecutar el backend Laravel en el mismo equipo.

## Primer inicio

```bash
cd mobile
npm install
npm start
```

Desde el menú de Expo se puede abrir el proyecto en Expo Go o en un emulador. La aplicación está fijada en orientación horizontal porque el flujo operativo está diseñado para tablets.

## Validaciones

```bash
npm run typecheck
npm run export:android
```

GitHub Actions ejecuta ambas validaciones en cada pull request.

## Alcance del prototipo

- Selector de cámaras con ocupación y estado de edición.
- Cuadrícula de posiciones con estados libre, ocupado y seleccionado.
- Modo de solo lectura cuando otra persona está editando una cámara.
- Acciones de escaneo y movimiento preparadas para conectarse con la API.
- Sesión activa y últimos movimientos simulados.

Todavía no incluye autenticación, acceso a cámara o código de barras, SQLite ni sincronización con Laravel.
