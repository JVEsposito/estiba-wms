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
];
