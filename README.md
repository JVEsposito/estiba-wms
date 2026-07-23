# Estiba WMS

Estiba WMS es una plataforma operacional para recepción, validación, tratamiento térmico, almacenamiento, inventario y despacho en una planta agroindustrial. Combina oficinas web para administración y supervisión con una aplicación Android orientada a tablets y PDA.

La base MySQL es la autoridad del estado confirmado. Las reglas de negocio viven en Laravel y las interfaces web y móvil consumen la misma API autenticada mediante Sanctum.

## Dominios actuales

```text
Administración transversal
├─ usuarios y dispositivos
├─ temporadas globales
├─ clientes globales
└─ migración controlada entre temporadas

Recepción de materia prima
├─ Romana
├─ Validación MP
├─ cuenta corriente de envases
└─ guías internas de despacho de envases

Frigorífico
├─ Validación de pallets/PT
├─ Prefrío
├─ Cámaras y estiba
├─ Cargas
├─ Andenes
└─ Despacho

Bodega de materiales
├─ catálogo estacional por cliente
├─ proveedores
├─ inventario y ubicaciones
├─ reservas y FIFO sugerido
├─ retiros y kardex
└─ despachos

Gestión
└─ panel gerencial de solo lectura
```

## Principios de diseño

- La temporada es una dimensión global administrada exclusivamente desde Accesos.
- Los clientes son maestros globales; Validación y Materiales mantienen configuraciones estacionales sobre el mismo cliente.
- Romana, Validación MP y Frigorífico comparten contexto, pero conservan ciclos de vida independientes.
- Un correlativo `REC-*` identifica una recepción contractual de Romana; no es un folio frigorífico.
- El folio identifica un bulto de producto o material dentro del dominio de inventario.
- Las operaciones críticas son transaccionales e idempotentes mediante UUID y hash del payload.
- Las ubicaciones actuales se separan del historial de movimientos.
- Los estados terminales y registros históricos no se eliminan físicamente.
- Las tablets nunca dependen directamente del ERP para operar.

## Flujos principales

### Producto terminado

```text
Validación PT
→ folio pendiente de Prefrío
→ carga y proceso térmico
→ verificación y aprobación
→ habilitación para almacenamiento
→ ubicación en cámara
→ disponibilidad para carga
→ andén y despacho
```

### Materia prima

```text
Romana
→ recepción REC-*
→ Validación MP
→ conteo real de envases y segregación
→ segmentos pendiente_lote
```

Validación MP todavía no genera el número de lote definitivo ni crea folios de Frigorífico.

### Materiales

```text
catálogo estacional por cliente
→ ingreso y ubicación
→ reserva FIFO sugerida
→ retiro parcial o total
→ kardex y trazabilidad
```

En cámaras de materiales una posición puede contener varias líneas o folios del mismo cliente. En cámaras de producto la posición continúa siendo exclusiva para un solo folio.

### Envases

```text
recepción o compra/arriendo
→ movimiento de existencia y cuenta corriente
→ revisión
→ guía interna GDE-*
→ confirmación o anulación compensatoria
```

Las guías de envases son documentos operacionales internos y no se presentan como DTE legales.

## Arquitectura

| Componente | Tecnología |
|---|---|
| API y reglas de negocio | PHP 8.3 + Laravel 13 |
| Base de datos | MySQL 8 |
| Oficinas web | Blade + JavaScript + CSS responsive |
| Aplicación móvil | Expo + React Native + TypeScript en `mobile/` |
| Persistencia local | AsyncStorage y bandejas por usuario/dispositivo en módulos compatibles |
| Autenticación | Laravel Sanctum |
| Diagnóstico local | Laravel Telescope restringido a loopback |
| Integración futura | Adaptadores API, Webservice, ODBC o archivos |
| Validación automática | GitHub Actions |

El repositorio es un monorepo: Laravel y las oficinas web viven en la raíz; la aplicación Android vive en `mobile/`.

## Oficinas web

| Ruta | Función |
|---|---|
| `/oficina/accesos` | Usuarios, dispositivos, clientes globales, temporadas y migración de ciclo |
| `/oficina/romana` | Pesaje, recepción, tara, cierre y Aviso de Recibo |
| `/oficina/envases/cuenta-corriente` | Existencia y cuenta corriente de envases |
| `/oficina/envases/despachos` | Guías internas de salida de envases |
| `/oficina/validacion` | Historial, importaciones y trazabilidad de Validación PT |
| `/oficina/validacion/catalogo` | Catálogo jerárquico estacional de Validación |
| `/oficina/prefrio` | Túneles, procesos, eventos y decisiones de supervisión |
| `/oficina/camaras` | Configuración y consulta de cámaras y andenes |
| `/oficina/cargas` | Preparación y seguimiento de cargas |
| `/oficina/materiales` | Catálogo, proveedores, inventario, despachos y kardex |
| `/oficina/gerencia` | Indicadores operacionales de solo lectura |

## Aplicación móvil

La aplicación habilita módulos según las capacidades del usuario:

- **Operación frigorífico:** Cámaras, movimientos, cargas y materiales.
- **Validación PT:** captura de pallets con catálogo persistente y bandeja offline.
- **Validación MP:** toma exclusiva de recepciones `REC-*`, conteo de envases y segregación.
- **Prefrío:** carga por posición, eventos térmicos, verificación y bandeja offline.

Validación PT y Validación MP se presentan en orientación vertical. Prefrío y la operación frigorífica utilizan orientación horizontal.

La versión nativa actual es `1.1.0`, con `versionCode` Android `2` y actualizaciones EAS habilitadas para cambios compatibles con ese runtime.

## Roles

```text
administrador
supervisor_frio
supervisor_materiales
despachador
operador_prefrio
operador_romana
camarero_frio
camarero_materiales
validador
validador_mp
consulta
```

Ocultar una acción en la interfaz nunca reemplaza la autorización del backend. Los controladores y servicios validan las capacidades del usuario y el ámbito operacional correspondiente.

## Temporadas y clientes

- `/oficina/accesos` es el único propietario de la creación, edición y activación de temporadas.
- Romana, Validación, Materiales, Frigorífico, Prefrío, Cargas y Despachos registran o consumen la temporada global.
- Los procesos históricos conservan la temporada con la que nacieron.
- La migración de ciclo copia catálogos estacionales y puede trasladar inventario vivo de Materiales cuando no existen reservas o despachos abiertos.
- Los clientes se crean y mantienen como maestros globales desde Accesos.
- Marcas, artículos, ítems y otras relaciones continúan siendo estacionales cuando corresponde.

## Documentación de producto

- [Glosario operacional](docs/glosario-operacional.md)
- [Alcance funcional actual](docs/alcance-mvp.md)
- [Reglas de negocio](docs/reglas-negocio.md)
- [Arquitectura](docs/arquitectura.md)
- [Configuración de cámaras y cargas](docs/configuracion-camaras-y-preparacion-cargas.md)
- [Segmentación operacional por área](docs/segmentacion-operacional-por-area.md)
- [Validación de pallets/PT](docs/MODULO_VALIDACION_PALLETS.md)
- [Prefrío](docs/MODULO_PREFRIO.md)
- [Romana](docs/MODULO_ROMANA.md)
- [Validación MP](docs/MODULO_VALIDACION_MP.md)
- [Cuenta corriente y despacho de envases](docs/MODULO_ENVASES.md)

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

Los datos de demostración solo se crean en `local` y `testing`. La contraseña común es `password`.

| Perfil | Usuario |
|---|---|
| Administrador | `administrador@estiba.local` |
| Supervisor de frío | `supervisor@estiba.local` |
| Camarero de frío | `operador@estiba.local` |
| Despachador | `despachador@estiba.local` |
| Validador PT | `validador@estiba.local` |
| Camarero de materiales | `camarero.materiales@estiba.local` |
| Supervisor de materiales | `supervisor.materiales@estiba.local` |
| Operador de Romana | `romana@estiba.local` |

Código de dispositivo local: `TABLET-01`.

## Importaciones XLSX

Las importaciones de Validación y Materiales admiten CSV y XLSX. El PHP del servidor debe tener habilitada la extensión `zip`:

```bash
php -m | findstr /I zip
```

CSV permanece como alternativa operacional, pero `ext-zip` es un requisito declarado por Composer.

## Diagnóstico local

Telescope solo se registra con `APP_ENV=local` y acepta conexiones desde loopback:

```text
http://127.0.0.1:8000/telescope
```

Puede desactivarse temporalmente con:

```dotenv
TELESCOPE_ENABLED=false
```

## Validación automática

Cada PR y cada push a `main` ejecutan:

```text
composer validate
composer install
npm ci y build Vite
Laravel Pint
migrate:fresh sobre MySQL
suite Laravel
TypeScript móvil
exportación Android
```

## Pendientes principales

- Crear lotes definitivos desde los segmentos de Validación MP.
- Definir asociaciones explícitas entre recepciones, lotes y procesos posteriores sin reutilizar identificadores.
- Implementar repaletizaje y genealogía de saldos.
- Extender la operación offline a los módulos que todavía dependen de conectividad.
- Integrar el ERP mediante adaptadores desacoplados.
- Incorporar telemetría automática y fotografías donde aporten valor operacional.
