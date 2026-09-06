<?php

$mode = strtolower((string) env('WMS_PLANNER_MODE', 'off'));
$compute = strtolower((string) env('WMS_PLANNER_COMPUTE', 'server'));
$horizon = strtolower((string) env('WMS_PLANNER_HORIZON', 'batch'));
$rolloutCamaras = array_values(array_unique(array_filter(array_map(
    static fn (string $camara): string => trim($camara),
    explode(',', (string) env('WMS_PLANNER_ROLLOUT_CAMERAS', '')),
))));

if (! in_array($mode, ['off', 'shadow', 'guided'], true)) {
    $mode = 'off';
}
if (! in_array($compute, ['server', 'tablet'], true)) {
    $compute = 'server';
}
if (! in_array($horizon, ['batch', 'rolling'], true)) {
    $horizon = 'batch';
}

return [
    /*
    |--------------------------------------------------------------------------
    | Despliegue del planificador
    |--------------------------------------------------------------------------
    |
    | off: estructuras disponibles sin dirigir la operación.
    | shadow: calcula y registra recomendaciones sin materializar trabajo.
    | guided: permite materializar una frontera corta de trabajo operativo.
    |
    */
    'mode' => $mode,

    /*
    |--------------------------------------------------------------------------
    | Lugar de cálculo y horizonte
    |--------------------------------------------------------------------------
    |
    | compute define quién simula/scorea las alternativas. Incluso cuando la
    | tablet calcula, el servidor conserva la autoridad sobre estado y reservas.
    |
    | batch conserva compatibilidad con planes estáticos. rolling permite que el
    | objetivo viva más que las maniobras actualmente materializadas.
    |
    */
    'compute' => $compute,
    'horizon' => $horizon,
    'frontier_max' => max(1, (int) env('WMS_PLANNER_FRONTIER_MAX', 4)),

    /*
    |--------------------------------------------------------------------------
    | Despliegue progresivo por cámara
    |--------------------------------------------------------------------------
    |
    | Cuando la lista está vacía, el modo global conserva el comportamiento
    | histórico. En guided, una lista no vacía limita la dirección a esos UUID
    | o códigos de cámara; el resto continúa observándose como shadow. Cambiar
    | el modo global a off siempre prevalece como rollback operacional.
    |
    */
    'rollout_camaras' => $rolloutCamaras,

    /*
    |--------------------------------------------------------------------------
    | Compatibilidad con la bandera histórica
    |--------------------------------------------------------------------------
    |
    | Los generadores existentes que todavía consultan la bandera booleana
    | continúan apagados salvo que se configure explícitamente o se active
    | guided. Esta clave podrá retirarse cuando todos migren a WMS_PLANNER_MODE.
    |
    */
    'generacion_automatica' => (bool) env(
        'WMS_PLANIFICADOR_AUTOMATICO',
        $mode === 'guided',
    ),

    /*
    |--------------------------------------------------------------------------
    | Lease de maniobras y pasos operacionales
    |--------------------------------------------------------------------------
    |
    | En rolling, el lease comienza como claim exclusivo del paso actual. El
    | destino físico se incorpora al mismo lease solo después del arbitraje del
    | servidor. Los pasos siguientes de una maniobra permanecen bloqueados.
    |
    */
    'reserva_tarea_minutos' => max(1, (int) env('WMS_RESERVA_TAREA_MINUTOS', 10)),
    'tarea_estancada_minutos' => max(5, (int) env('WMS_PLANNER_STALE_TASK_MINUTES', 30)),
];
