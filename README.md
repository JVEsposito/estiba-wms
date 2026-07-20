# Estiba WMS

Aplicación orientada a tablets para gestionar la ubicación y el movimiento de bultos en cámaras frigoríficas. El objetivo del MVP es reemplazar el plano de estiba en papel por un mapa operativo, auditable y preparado para trabajar con conectividad intermitente.

> Esta documentación define el dominio y el alcance antes de modificar migraciones o implementar reglas de negocio.

## Prioridad del MVP

1. Crear cámaras y configurar sus posiciones.
2. Abrir una sesión de edición sobre el plano de una cámara.
3. Ubicar, reubicar dentro de una cámara, trasladar entre cámaras y retirar bultos identificados por un folio único.
4. Mantener la ocupación actual y el historial completo de movimientos.
5. Permitir consulta concurrente, pero una sola sesión de edición por cámara.
6. Sincronizar operaciones de tablet de forma segura e idempotente.

En este dominio, una **estiba** es la asignación espacial de bultos a posiciones de una cámara. La posición física la ocupa un bulto —pallet completo o saldo— identificado por su folio.

## Decisiones principales

- El folio se crea automáticamente durante su primera ubicación si todavía no existe.
- El ingreso a cámara y la ubicación inicial representan la misma operación.
- Un traslado entre cámaras libera el origen y ocupa el destino dentro de una única operación transaccional.
- No habrá un módulo independiente para crear folios.
- Cada cambio del plano pertenece a una sesión de estiba y queda auditado.
- Una cámara puede ser consultada por varias personas, pero solo una puede editarla a la vez.
- El repaletizaje y el control de temperatura quedan fuera del MVP.
- Las órdenes de carga se incorporan sobre el núcleo estable de estibas; el despacho físico hacia andenes permanece como una etapa posterior.
- La arquitectura dejará una interfaz preparada para una futura integración con el ERP utilizado por la empresa, sin acoplar el MVP a Suit Export.

## Arquitectura prevista

| Componente | Tecnología |
|---|---|
| API y reglas de negocio | PHP 8.3 + Laravel 13 |
| Base de datos central | MySQL |
| Interfaz web operacional y de oficina | Blade + JavaScript + CSS responsive |
| Cliente nativo para tablets | Expo + React Native + TypeScript en `mobile/` |
| Persistencia local | AsyncStorage y bandejas offline por usuario/dispositivo en los módulos compatibles |
| Integración futura | Adaptadores de entrada/salida desacoplados |
| Repositorio | Monorepo: Laravel y web en la raíz; cliente nativo en `mobile/` |

La base central será la autoridad del estado confirmado. Tanto la interfaz web como el cliente nativo para tablets utilizan la API Laravel y conservan un identificador idempotente por operación. Los módulos móviles de Validación y Prefrío conservan además su último catálogo o estado conocido y una bandeja local para tolerar interrupciones de red.

## Documentación de producto

- [Glosario operacional](docs/glosario-operacional.md)
- [Alcance del MVP](docs/alcance-mvp.md)
- [Reglas de negocio](docs/reglas-negocio.md)
- [Arquitectura propuesta](docs/arquitectura.md)
- [Prueba de escritura desde Expo Go hacia MySQL](docs/prueba-escritura-mysql.md)
- [Configuración de cámaras y preparación de cargas](docs/configuracion-camaras-y-preparacion-cargas.md)
- [Segmentación operacional por área](docs/segmentacion-operacional-por-area.md)
- [Módulo de Validación de pallets](docs/MODULO_VALIDACION_PALLETS.md)
- [Módulo de Prefrío](docs/MODULO_PREFRIO.md)

Estas definiciones son la referencia previa para diseñar migraciones, endpoints, modelos y pantallas.

## Estado del proyecto

El backend cuenta con Laravel y Sanctum para autenticación API. El esquema central protege la ocupación única, las sesiones de edición, la trazabilidad y la idempotencia. La interfaz landscape para tablets permite seleccionar cámaras, abrir y cerrar estibas, ubicar folios y moverlos dentro de una cámara o hacia otra.

La configuración de cámaras se realiza desde PC en `/oficina/camaras`; desde la
misma pantalla el administrador puede crear, editar, activar y desactivar
andenes. El acceso de oficina no solicita código de tablet. Los perfiles de frío
y materiales reciben cámaras, sesiones y acciones de su propia área;
`despachador` y `consulta` pueden observar ambas sin abrir sesiones
operacionales.

Validación dispone de dos entradas diferentes:

- `/oficina/validacion`: temporadas, artículos, orígenes, combinaciones, importación y trazabilidad.
- Aplicación móvil: captura rápida para el perfil `validador`, con catálogo persistente y bandeja de salida.

Prefrío dispone de:

- `/oficina/prefrio`: configuración de túneles, tablero, procesos, historial y decisiones de supervisión.
- Aplicación móvil: operación por túnel, plano de dos lados desde el fondo hacia la entrada, escaneo, eventos térmicos y bandeja offline para `operador_prefrio`.

## Requisito para importar XLSX

La carga masiva de Validación acepta CSV y XLSX. Los archivos XLSX son contenedores
ZIP, por lo que el PHP que ejecuta Laravel debe tener habilitada la extensión
`zip`. El proyecto la declara como requisito para impedir despliegues que fallen
recién al cargar una planilla.

En Laragon para Windows:

1. Abrir **Menú > PHP > Extensions** y habilitar `zip`.
2. Si la extensión no aparece en el menú, abrir el `php.ini` indicado por
   `php --ini` y habilitar la línea `extension=zip`.
3. Reiniciar todos los servicios de Laragon.
4. Verificar en la terminal de Laragon con `php -m | findstr /I zip`.

Si todavía no es posible habilitar ZIP, la misma importación puede ejecutarse
temporalmente mediante CSV. Es importante comprobar el PHP del servidor web y no
solamente otro PHP instalado en el equipo.

## Puesta en marcha local

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Los datos de demostración solo se crean en entornos `local` y `testing`:

- Usuario: `operador@estiba.local` (`camarero_frio`)
- Contraseña: `password`
- Código de tablet: `TABLET-01`

Para probar la configuración desde oficina:

- Usuario: `supervisor@estiba.local`
- Contraseña: `password`

Para probar materiales:

- Camarero: `camarero.materiales@estiba.local`
- Supervisor: `supervisor.materiales@estiba.local`
- Contraseña: `password`

Para editar, redimensionar, desactivar o reactivar cámaras:

- Usuario: `administrador@estiba.local`
- Contraseña: `password`

Para probar la API de órdenes de carga:

- Usuario: `despachador@estiba.local`
- Contraseña: `password`

Para administrar Validación desde PC:

- Ruta: `/oficina/validacion`
- Administrador: `administrador@estiba.local`
- Supervisor en consulta: `supervisor@estiba.local`
- Contraseña: `password`

Para validar pallets desde la aplicación móvil:

- Usuario: `validador@estiba.local`
- Contraseña: `password`
- Código de tablet: `TABLET-01`

## Orden de implementación propuesto

1. [x] Auditar el esquema exploratorio.
2. [x] Reconstruir migraciones para cámaras, posiciones, folios, sesiones y movimientos.
3. [x] Instalar Sanctum y habilitar las rutas API.
4. [x] Implementar servicios transaccionales y completar las pruebas del dominio.
5. [x] Publicar el contrato de la API REST.
6. [x] Construir el flujo conectado principal para tablets.
7. [ ] Incorporar sincronización offline y resolución de conflictos en todos los módulos operacionales.
8. [x] Completar la interfaz de cargas y el despacho físico hacia andenes.
9. [x] Incorporar notificaciones persistentes y polling resiliente en la APK.
10. [x] Completar Validación con oficina, importación, PDA y bandeja local.
