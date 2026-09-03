# Recepción rolling desde Prefrío

## Objetivo

Cuando un proceso de Prefrío es aprobado, el WMS puede convertir automáticamente los pallets que requieren almacenamiento en un objetivo operacional `recepcion_tunel`.

El objetivo no preasigna cámara ni posición. Cada pallet se incorpora como una tarea `ubicacion_inicial` con horizonte `rolling`; la bandeja de tablet de PR #250 calcula una frontera corta y el servidor arbitra/reserva el destino físico.

## Activación

La generación conserva rollback explícito:

```env
WMS_PLANIFICADOR_AUTOMATICO=false
```

Con la bandera apagada, aprobar Prefrío mantiene el comportamiento histórico y no crea planes.

Con la bandera activada, los objetivos generados por este flujo fijan en su propio contexto:

```text
planner_horizon = rolling
```

El comportamiento no depende del horizonte global configurado para otros planes.

## Universo del objetivo

Solo se materializan tareas para asignaciones del proceso que estén aprobadas y cuyo folio:

- sea un pallet completo activo;
- todavía no posea ubicación actual;
- no tenga una asignación vigente a una carga `directa_prefrio`.

Saldos/REPA siguen fuera del planificador. La salida directa de Prefrío continúa bajo el flujo de carga existente y no compite por una posición de cámara.

## Origen y destino

El túnel se conserva como origen lógico en el contexto de la tarea:

- proceso de Prefrío;
- túnel;
- posición dentro del túnel;
- marca, formato, exportadora, variedad y calibre disponibles.

La tarea se crea sin `camara_destino_id` ni `posicion_destino_id`. El destino exacto aparece después, cuando la frontera rolling es materializada por el servidor.

## Idempotencia

Cada proceso de Prefrío puede originar como máximo un plan mediante la referencia estable:

```text
referencia_tipo = proceso_prefrio
referencia_id   = <uuid del proceso>
```

La base de datos protege la combinación con un índice único. Un retry de aprobación o una carrera concurrente recupera el plan ya creado en lugar de duplicar el objetivo.

## Condición de término

`recepcion_tunel` posee una condición de término específica: el plan se completa únicamente cuando todas sus tareas planificadas están `completada`.

Una tarea cancelada no se interpreta como pallet recibido. En ese caso el plan permanece abierto para hacer visible la anomalía y permitir una resolución posterior.

## Límites de PR #251

Este incremento no incorpora todavía:

- presencia del camión en andén;
- replanificación desde cámara hacia despacho directo;
- concentración de cargas;
- reservas SAG;
- retenciones automáticas;
- movimientos de oportunidad;
- evacuación de cámaras.

La llegada de camión y la prioridad de salida directa corresponden al siguiente incremento del programa.
