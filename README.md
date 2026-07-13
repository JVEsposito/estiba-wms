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
- Cargas y despachos se abordarán después del núcleo de estibas y movimientos.
- La arquitectura dejará una interfaz preparada para una futura integración con el ERP utilizado por la empresa, sin acoplar el MVP a Suit Export.

## Arquitectura prevista

| Componente | Tecnología |
|---|---|
| API y reglas de negocio | PHP 8.3 + Laravel 13 |
| Base de datos central | MySQL |
| Aplicación para tablets | React Native + TypeScript |
| Persistencia local | SQLite |
| Integración futura | Adaptadores de entrada/salida desacoplados |
| Repositorio | Monorepo: Laravel en la raíz y cliente móvil en `mobile/` |

La base central será la autoridad del estado confirmado. La aplicación móvil mantendrá una cola local de operaciones para tolerar interrupciones de red y resolver conflictos al sincronizar.

## Documentación de producto

- [Glosario operacional](docs/glosario-operacional.md)
- [Alcance del MVP](docs/alcance-mvp.md)
- [Reglas de negocio](docs/reglas-negocio.md)
- [Arquitectura propuesta](docs/arquitectura.md)

Estas definiciones son la referencia previa para diseñar migraciones, endpoints, modelos y pantallas.

## Estado del proyecto

El backend cuenta con Laravel y Sanctum para autenticación API. El esquema central fue reconstruido para proteger la ocupación única, las sesiones de edición, la trazabilidad y la idempotencia. Las migraciones exploratorias de temperatura, repaletizaje y despacho fueron retiradas del núcleo actual.

## Orden de implementación propuesto

1. [x] Auditar el esquema exploratorio.
2. [x] Reconstruir migraciones para cámaras, posiciones, folios, sesiones y movimientos.
3. [x] Instalar Sanctum y habilitar las rutas API.
4. [ ] Implementar servicios transaccionales y completar las pruebas del dominio.
5. [ ] Publicar el contrato de la API REST.
6. [ ] Construir el flujo principal para tablets.
7. [ ] Incorporar sincronización offline y resolución de conflictos.
8. [ ] Añadir cargas y despachos como módulo posterior.
