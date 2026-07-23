# Segmentación operacional por área

## Autoridad

El usuario autenticado determina el ámbito operacional. Las tablets son dispositivos autorizados e identificables, pero no poseen un área fija: una misma tablet puede utilizarse en distintos módulos según el perfil que inicia sesión.

La autorización se resuelve en Laravel mediante Gates, capacidades y servicios de alcance. Ocultar una pantalla o botón nunca sustituye esa validación.

Las capacidades deciden, entre otros:

- módulos web y móviles visibles;
- cámaras consultables y operables;
- apertura de sesiones y cierres forzosos;
- administración de cámaras, túneles, temporadas, clientes y catálogos;
- operación de Romana;
- Validación PT y Validación MP;
- operación y supervisión de Prefrío;
- consulta y gestión de cargas;
- inventario, retiros, despachos y kardex de Materiales;
- cuenta corriente y despacho de Envases;
- panel gerencial.

## Roles vigentes

### `administrador`

Acceso transversal y administrativo. Puede gestionar accesos, clientes globales, temporadas, cámaras, túneles y catálogos, además de operar o supervisar los dominios según sus Gates.

### `supervisor_frio`

Opera y supervisa el área de frío. Puede consultar Romana, administrar decisiones de Prefrío, operar cámaras de producto y gestionar acciones de supervisión asociadas.

No administra accesos, temporadas globales ni la estructura de túneles cuando esa acción está reservada al administrador.

### `supervisor_materiales`

Opera y supervisa cámaras e inventario de Materiales. Puede administrar despachos, kardex, retiros, correcciones autorizadas y cierres forzosos de su área.

### `despachador`

Consulta los ámbitos necesarios para preparar y ejecutar cargas o despachos. No abre sesiones operacionales de cámara salvo que otra capacidad explícita lo autorice.

También puede consultar Romana, pero no registrar pesajes.

### `operador_prefrio`

Opera túneles y procesos térmicos:

- crea procesos cuando corresponde;
- carga y retira folios antes del inicio;
- confirma armado;
- inicia;
- registra eventos;
- envía a verificación.

No administra túneles ni toma decisiones terminales.

### `operador_romana`

Registra y actualiza recepciones, confirma ingreso, captura tara y cierra el pesaje. No obtiene por ese rol permisos sobre Validación MP, Frigorífico o Materiales.

### `camarero_frio`

Opera únicamente cámaras de producto, movimientos, tareas de carga y acciones móviles autorizadas del frigorífico.

### `camarero_materiales`

Opera únicamente cámaras de Materiales y ejecuta retiros sobre despachos existentes. Puede seleccionar líneas concretas dentro de una posición multilínea.

### `validador`

Opera Validación de pallets/PT mediante catálogo, captura y bandeja móvil. No opera Validación MP, Romana, Cámaras, Cargas o Materiales.

### `validador_mp`

Opera Validación MP sobre recepciones `REC-*`: toma exclusiva, conteo real de envases, revisión y segregación. No crea folios de producto terminado.

### `consulta`

Lectura transversal sobre los módulos habilitados. No ejecuta mutaciones ni accede automáticamente a auditorías restringidas como el kardex completo.

## Separación de dominios

Compartir cliente o temporada no concede permisos cruzados.

Ejemplos:

- `operador_romana` no se convierte en `validador_mp`.
- `validador_mp` no puede validar pallets/PT.
- `operador_prefrio` no puede mover folios en Cámaras.
- `camarero_materiales` no ve cámaras de producto.
- `camarero_frio` no retira materiales.
- `consulta` no adquiere permisos de escritura por poder ver un panel.

## Aplicación móvil

Los módulos móviles se habilitan por capacidades:

| Módulo | Orientación habitual | Perfiles principales |
|---|---|---|
| Operación frigorífico | Horizontal | Camareros y supervisores según área |
| Validación PT | Vertical | `validador` |
| Validación MP | Vertical | `validador_mp` |
| Prefrío | Horizontal | `operador_prefrio`, supervisión y administración |

Un usuario con acceso a más de un módulo recibe un selector de área. Un perfil con un único módulo entra directamente en él.

## Cierre forzoso de sesiones

El cierre normal corresponde al dueño de la sesión. La intervención administrativa utiliza:

```http
POST /api/sesiones/{sesion}/cerrar-forzosamente
```

Requiere:

- token de oficina;
- motivo;
- autorización para supervisar el área de la cámara.

Conserva:

- usuario responsable;
- fecha;
- motivo;
- sesión afectada;
- estado de cierre forzado.

Las operaciones offline asociadas a una sesión que dejó de ser válida deben entrar en conflicto y no aplicarse silenciosamente.

## Administración transversal

Accesos concentra:

- usuarios;
- dispositivos;
- clientes globales;
- temporadas;
- activación y migración de ciclos.

Validación, Materiales, Romana y los otros dominios consumen esas dimensiones, pero no pueden administrarlas desde sus propias oficinas salvo las configuraciones estacionales específicas que les correspondan.

## Despliegue de cambios de permisos

1. Identificar sesiones operacionales activas.
2. Programar una ventana controlada cuando el cambio afecte roles o contratos móviles.
3. Poner Laravel en mantenimiento si existen migraciones incompatibles.
4. Traer el código y ejecutar migraciones.
5. Limpiar cachés y compilar la interfaz web.
6. Publicar una APK nueva cuando cambien dependencias nativas o runtime.
7. Publicar EAS Update solo para cambios compatibles con la versión instalada.
8. Levantar Laravel.
9. Revocar tokens cuando las capacidades antiguas no deban permanecer activas.
10. Volver a iniciar sesión y validar cada perfil con una matriz de permisos.
