# Arquitectura

## Objetivo

Estiba WMS debe mantener integridad transaccional en MySQL, ofrecer interfaces simples para operación y supervisión, y tolerar conectividad intermitente sin permitir que una interfaz sobrescriba silenciosamente el estado confirmado.

Las reglas de negocio pertenecen al backend. Las oficinas web y la aplicación móvil son clientes de la misma API y no sustituyen las autorizaciones ni las validaciones de Laravel.

## Componentes

| Componente | Responsabilidad |
|---|---|
| Laravel 13 / PHP 8.3 | API, autenticación, permisos, transacciones, idempotencia y documentos |
| MySQL 8 | Estado central, restricciones, ubicaciones, inventario y auditoría |
| Blade + JavaScript + CSS | Oficinas conectadas de administración, operación y supervisión |
| Expo + React Native + TypeScript | Aplicación Android para tablets y PDA |
| AsyncStorage | Caché y bandejas offline en los módulos compatibles |
| Laravel Sanctum | Tokens de oficina y dispositivo |
| Laravel Telescope | Diagnóstico exclusivo del entorno local |
| GitHub Actions | Composer, build, estilo, migraciones, pruebas y bundle Android |

El repositorio es un monorepo:

- Laravel, oficinas y documentación viven en la raíz.
- La aplicación nativa vive en `mobile/`.
- Los workflows viven en `.github/workflows/`.

## Capas

### Presentación

- Oficinas web bajo `/oficina/*`.
- Aplicación Android con módulos habilitados por capacidades.
- Formularios y pantallas muestran los conflictos informados por la API.

### Aplicación

- Controllers y Form Requests reciben y validan comandos.
- Resources publican contratos estables.
- Gates y servicios de alcance interpretan roles y áreas.
- Servicios de dominio ejecutan las reglas transaccionales.

### Dominio y persistencia

- Modelos Eloquent representan el estado y las relaciones.
- Restricciones únicas y llaves foráneas protegen la integridad incluso si una interfaz falla.
- Los movimientos, eventos y asientos conservan la evidencia histórica.
- Los registros terminales se anulan o compensan; no se borran para reescribir la historia.

## Dimensiones transversales

### Temporada global

`temporadas` es la dimensión operacional compartida. Accesos es el único módulo que puede crear, editar, activar o migrar una temporada.

Los procesos nuevos registran la temporada activa cuando corresponde. Los documentos y procesos históricos conservan su temporada original.

La migración de temporada:

- copia catálogos estacionales de Validación y Materiales;
- reconstruye proyecciones para la PDA;
- puede trasladar inventario vivo de Materiales;
- conserva folio, ubicación, cantidades y kardex;
- se bloquea ante reservas o despachos abiertos;
- no transforma recepciones, validaciones, cargas ni procesos históricos.

### Cliente global

`clientes` representa el maestro transversal. Validación y Materiales mantienen configuraciones estacionales enlazadas al mismo cliente. Romana y Envases utilizan el maestro global y guardan snapshots cuando el documento debe conservar el dato contractual.

## Separación de dominios

### Recepción de materia prima

```text
Romana
→ Validación MP
→ segmentos pendiente_lote
```

Romana conserva el expediente contractual y el pesaje. Validación MP toma la recepción y confirma cantidades reales y segregaciones. Ninguno de estos procesos crea automáticamente folios de Frigorífico.

### Frigorífico

```text
Validación PT
→ Prefrío
→ Cámara
→ Carga
→ Andén / despacho
```

Validación PT crea el folio. Prefrío administra el tratamiento térmico y la habilitación. Cámaras mantiene la ubicación. Cargas reserva y organiza la salida.

### Materiales

Materiales mantiene maestros estacionales, proveedores, fichas de inventario, reservas, retiros, despachos y kardex. Puede compartir una posición entre varios folios del mismo cliente, sin alterar la exclusividad de las cámaras de producto.

### Envases

Envases mantiene existencia y cuenta corriente mediante movimientos firmados. Las guías internas descuentan o compensan esos movimientos sin borrar historia.

## Identidades

- UUID interno: identidad técnica de las entidades.
- `numero_folio`: identidad operacional de un bulto.
- `REC-AAMM-####`: expediente de recepción de Romana.
- `PF-AAAA-NNNNNN`: proceso térmico.
- `CAR-*`: carga de producto.
- `MAT-DES-*`: despacho de materiales.
- `GDE-AAMM-NNNN`: guía interna de envases.

Los correlativos de un dominio no se reutilizan como identidad de otro.

## Ubicaciones y movimientos

La ocupación vigente se mantiene en una relación separada del historial.

Una operación de ubicación o movimiento debe:

1. autenticar usuario y dispositivo;
2. validar las capacidades y el ámbito;
3. validar las sesiones de todas las cámaras afectadas;
4. bloquear cámaras, posiciones, folios y ubicaciones en un orden estable;
5. comprobar versiones conocidas;
6. aplicar las reglas de contenido y compatibilidad;
7. crear el movimiento y modificar la ubicación en la misma transacción;
8. incrementar las versiones resultantes;
9. registrar el resultado idempotente;
10. confirmar o revertir todo el bloque.

En cámaras de producto una posición admite un folio. En cámaras de materiales una posición admite varias líneas siempre que pertenezcan al mismo cliente.

## Folios

Un folio puede nacer en Validación PT o, para contingencias y Materiales, durante una ubicación inicial autorizada.

El folio conserva, entre otros:

- temporada;
- tipo de bulto;
- estado operacional;
- condición térmica;
- habilitación para almacenamiento;
- origen de sistema;
- datos externos e integración;
- ubicación actual e historial.

La consulta previa a una ubicación recupera los datos existentes y evita reescribir desde Cámaras la información nacida en Validación.

## Prefrío

Túneles y cámaras son activos distintos. Los procesos térmicos tienen su propia máquina de estados, posiciones, versión, eventos y resultados por folio.

La aprobación térmica habilita el folio, pero no crea una ubicación. La primera ubicación en cámara lo promueve a disponible para cargas.

## Idempotencia

Las operaciones críticas utilizan `operacion_id` UUID y un hash del payload normalizado.

- Mismo UUID y mismo contenido: se devuelve el resultado previo.
- Mismo UUID y contenido diferente: conflicto.
- Conflicto de versión o integridad: no se aplica un estado parcial.

Esto se utiliza en movimientos, Validación, Prefrío, Romana, guías y otros flujos sensibles.

## Operación offline

### Validación PT

Conserva catálogo y bandeja local por usuario y dispositivo.

### Prefrío

Conserva túneles, procesos, folios elegibles y comandos pendientes. Un error o conflicto detiene las acciones posteriores del mismo proceso hasta reconciliar el estado.

### Otros módulos

Operación frigorífica, Materiales, Romana y Validación MP todavía dependen principalmente de conectividad. Su evolución offline debe reutilizar el mismo principio: persistir antes de transmitir, UUID estable, orden y conflicto visible.

## API

La API se organiza por dominio bajo `/api/*`:

- acceso y administración;
- gerencia;
- Romana;
- Envases;
- Validación PT;
- Validación MP;
- Prefrío;
- Cámaras y movimientos;
- Cargas y andenes;
- Materiales;
- notificaciones.

Todas las rutas operacionales requieren `auth:sanctum`; los grupos agregan Gates según consulta, operación, supervisión o administración.

## Seguridad

- HTTPS obligatorio fuera de redes locales controladas.
- Tráfico HTTP habilitado únicamente para la operación Android en LAN cuando sea necesario.
- Tokens asociados a sesiones y dispositivos.
- Secretos fuera del repositorio.
- ODBC futuro de solo lectura.
- Auditoría de accesos, operaciones y cierres forzados.
- Telescope solo en `local` y restringido a loopback.
- Respaldos de MySQL y procedimiento de recuperación obligatorios para producción.

## Integración ERP

Las tablets no consultan directamente el ERP. La integración futura debe implementarse mediante adaptadores desacoplados:

- API o Webservice;
- ODBC de solo lectura;
- archivos;
- importaciones manuales.

El ERP puede enriquecer maestros o datos descriptivos, pero la WMS conserva la autoridad sobre ubicaciones, movimientos y evidencias operacionales.

## Pruebas y CI

La integración automática utiliza MySQL real y valida:

- Composer y requisitos de plataforma;
- compilación web;
- Laravel Pint;
- esquema limpio mediante migraciones;
- suite Laravel;
- TypeScript;
- exportación Android.

Las pruebas de dominio deben cubrir idempotencia, concurrencia, temporadas, autorizaciones, estados terminales, movimientos compensatorios y aislamiento entre dominios.

## Evolución pendiente

- creación de lotes definitivos desde Validación MP;
- asociación explícita entre recepción, lote y procesos posteriores;
- repaletizaje con genealogía de folios;
- offline ampliado;
- integración ERP;
- telemetría y evidencia fotográfica.
