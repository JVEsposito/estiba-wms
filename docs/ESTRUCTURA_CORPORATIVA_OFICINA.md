# Estructura corporativa de Oficina

Esta entrega adopta la identidad del PR 266 en la cabecera y la navegación. Se apoya
en main con la consulta y bandeja de discrepancias de los PR 265 y 267 integradas.
Las pantallas de trabajo conservan sus funciones y se migrarán por separado.

## Qué cambia

- Cabecera azul marino con ESTIBA, área y oficina actual, usuario y cierre de sesión.
- Menú lateral con los cinco dominios y las oficinas del dominio seleccionado.
  El enlace activo tiene texto, borde y `aria-current`; no depende solo del color.
- A partir de 1200 px el menú permanece visible y tiene desplazamiento propio.
  Por debajo se abre con Menú, sin cubrir ni bloquear los formularios de trabajo.
  Escape lo cierra y devuelve el foco al botón. Saltar al contenido evita recorrerlo.
- Controles de 44 px como mínimo y 56 px con puntero táctil.
- Apariencia inicial clara profesional. Las preferencias guardadas y las cuatro
  apariencias existentes continúan disponibles en el menú lateral.
- Consulta de contexto al iniciar sesión/abrir una oficina y mediante Actualizar
  contexto. No existe sondeo periódico ni consultas masivas adicionales.

## Contexto verificable

`GET /api/oficina/contexto` requiere Sanctum y el mismo acceso de Oficina que el
inicio de sesión. Devuelve únicamente la planta configurada y la temporada activa
mediante `ServicioTemporadaActiva::buscar()`, sin crear ni activar registros.
La respuesta no se almacena en caché. No se amplían permisos operacionales.

El nombre de planta es opcional: `ESTIBA_PLANTA_NOMBRE` en `.env`, leído mediante
`config/oficina.php`. Si falta, se muestra Sin configurar. No se adopta una empresa
de las maquetas ni se deduce la planta del usuario. La temporada ausente se muestra
como Sin temporada activa; una consulta fallida muestra Sin verificar.

La hora corresponde a la consulta del contexto. No afirma que inventario, tareas
o datos térmicos estén sincronizados. Las respuestas de una sesión anterior se
descartan y las consultas tienen tiempo máximo de espera.

## Continuidad de funciones

| Función anterior | Ubicación después |
| --- | --- |
| Materia Prima, Frigorífico, Materiales, Administración y Consultas | Áreas del menú lateral |
| Oficinas de cada dominio | Segundo grupo del mismo menú |
| Permisos y módulos asignados | Mismos filtros y destinos accesibles |
| Usuario y perfil | Cabecera, con indicación Solo consulta cuando corresponde |
| Cerrar sesión | Mismo botón e identificador, gestionado por cada módulo |
| Cuatro apariencias | Apariencia del contenido, en el menú lateral |
| Expediente lateral de Romana | Conservado; calcula su posición desde la nueva cabecera |
| Formularios, tablas, acciones y paneles de los módulos | Conservados en el espacio de trabajo |
| Funciones eliminadas | Ninguna |

Los identificadores usados por los scripts de cada módulo se conservan. Los nuevos
estilos tienen alcance propio y los selectores genéricos heredados de formularios
excluyen la cabecera, en lugar de añadir otra capa de `!important`.

## Validación y despliegue

`npm run design:check`, `npm run test:js`, `npm run build` y la suite PHP verifican
tokens, compilación, contrato de contexto y estructura de los cinco dominios.
Actions conserva HTML de las cinco oficinas en `estiba-catalogo-visual/oficina`
para comprobar la composición con los assets del mismo commit. Son plantillas sin
sesiones ni datos operacionales; las APIs requieren autenticación.

No hay migraciones ni cambios móviles. Tras actualizar: regenerar autoload,
compilar assets, renovar cachés y comprobar la aplicación. El nombre de planta es
opcional y no bloquea el despliegue.

Siguiente capacidad del plan: registro ambiental manual horario, antes de la
pantalla Operación ahora. Esta entrega no registra temperaturas ni crea avisos.
