<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Generación automática de planes
    |--------------------------------------------------------------------------
    |
    | La base de planes puede consultarse y probarse sin intervenir la operación
    | actual. Los generadores de los próximos incrementos deberán respetar esta
    | bandera antes de crear trabajo para las tablets.
    |
    */
    'generacion_automatica' => (bool) env('WMS_PLANIFICADOR_AUTOMATICO', false),

    /*
    |--------------------------------------------------------------------------
    | Lease de tareas operacionales
    |--------------------------------------------------------------------------
    |
    | Una tarea asumida y su destino quedan reservados por este intervalo. La
    | tablet debe renovar el lease mientras la operación física continúa.
    |
    */
    'reserva_tarea_minutos' => max(1, (int) env('WMS_RESERVA_TAREA_MINUTOS', 10)),
];
