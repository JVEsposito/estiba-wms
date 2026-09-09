# Interfaz operacional de Prefrío

Esta entrega adopta el lenguaje visual corporativo de ESTIBA en
`/oficina/prefrio`. La pantalla pasa a funcionar como un centro de control
térmico claro y denso: primero muestra la vigencia de la consulta y sus
indicadores, después los túneles y procesos, y finalmente el expediente del
ciclo seleccionado.

## Continuidad funcional

No se eliminan funciones ni se cambia su ubicación. Siguen disponibles:

- configuración de túneles y posiciones;
- creación y filtrado de procesos;
- confirmación de armado e inicio con fecha y hora operacional;
- registro de inversión, pausa, reanudación, deshielo y lectura;
- envío a verificación y resultado por folio;
- aprobación, reproceso y cancelación;
- consulta del plano, la línea de tiempo y las métricas del proceso;
- corrección administrativa auditada del historial.

La selección de procesos también funciona con teclado mediante `Enter` o
barra espaciadora. El estado de la vista diferencia una consulta en curso, una
lectura vigente y un error. Aprobar, reprocesar, cancelar, corregir o registrar
una acción vuelve a consultar el resumen para evitar indicadores obsoletos.

## Contratos conservados

La interfaz continúa consumiendo los contratos existentes:

```text
GET  /api/prefrio/tuneles
GET  /api/prefrio/procesos
GET  /api/prefrio/resumen
GET  /api/prefrio/procesos/{id}
POST /api/prefrio/procesos
POST /api/prefrio/procesos/{id}/confirmar-armado
POST /api/prefrio/procesos/{id}/iniciar
POST /api/prefrio/procesos/{id}/eventos/{tipo}
POST /api/prefrio/procesos/{id}/verificar
POST /api/prefrio/procesos/{id}/aprobar
POST /api/prefrio/procesos/{id}/reprocesar
POST /api/prefrio/procesos/{id}/cancelar
POST /api/administracion/prefrio/tuneles
PUT  /api/administracion/prefrio/tuneles/{id}
PUT  /api/administracion/prefrio/procesos/{id}/corregir
```

No se agregan endpoints, migraciones, permisos ni reglas de negocio. Los IDs de
formularios, paneles y acciones permanecen intactos para conservar el contrato
con `resources/js/office-prefrio.js`.

## Criterio visual

- azul marino para estructura y encabezados;
- superficies claras y bordes definidos para lectura operacional;
- azul para la acción principal y naranja solo como acento;
- señales con texto explícito para proceso, advertencia, aprobación y bloqueo;
- texto blanco o azul muy claro sobre paneles navy, sin depender del modo de
  tema y sin gradientes decorativos;
- tabla compacta con fila seleccionada, foco visible y desplazamiento propio.

Los encabezados navy fijan pares de color de alto contraste. El significado de
los estados no depende únicamente del color: cada señal conserva su etiqueta.

## Verificación

```bash
npm run design:check
npm run test:js
npm run build
php artisan test --filter=OficinaPrefrioTest
```
