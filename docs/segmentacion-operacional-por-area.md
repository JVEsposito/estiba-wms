# Segmentación operacional por área

## Autoridad

El usuario autenticado determina el ámbito. Las tablets continúan siendo
dispositivos autorizados e identificables, pero no poseen un área propia. Una
misma tablet puede utilizarse para frío o materiales según el perfil que inicie
sesión.

`AlcanceOperacionalUsuario` es la fuente central para decidir:

- cámaras visibles y operables;
- creación y supervisión de cámaras;
- apertura de sesiones y cierres forzosos;
- consulta y gestión de cargas de productos;
- consulta, gestión, retiro, cancelación y kardex de materiales;
- capacidades devueltas a oficina y aplicación móvil.

## Roles

- `administrador`: acceso transversal operacional y administrativo.
- `supervisor_frio`: opera y supervisa cámaras de productos.
- `supervisor_materiales`: opera y supervisa cámaras de materiales.
- `despachador`: consulta ambas áreas, gestiona cargas y despachos, pero no abre
  sesiones de cámara.
- `camarero_frio`: opera únicamente cámaras de productos.
- `camarero_materiales`: opera únicamente cámaras de materiales y ejecuta
  retiros sobre despachos existentes.
- `consulta`: lectura transversal sin mutaciones ni kardex de auditoría.

## Migración

La migración transforma `operador` en `camarero_frio` y `supervisor` en
`supervisor_frio`. Los tokens de esos usuarios se revocan para que vuelvan a
autenticarse y reciban las nuevas capacidades. Las sesiones cerradas,
movimientos y demás auditorías históricas no se modifican.

## Cierre forzoso

El cierre normal permanece en la tablet del dueño de la sesión. La intervención
administrativa utiliza:

```http
POST /api/sesiones/{sesion}/cerrar-forzosamente
```

Requiere token de oficina, motivo y autorización para supervisar el área de la
cámara. Registra estado de cierre forzado, usuario, fecha, motivo y sesión.

## Despliegue

1. Cerrar sesiones operacionales activas.
2. Poner Laravel en mantenimiento.
3. Traer el código y ejecutar migraciones.
4. Limpiar cachés y compilar la interfaz web.
5. Publicar la actualización móvil.
6. Levantar Laravel.
7. Volver a iniciar sesión en oficina y tablets.
