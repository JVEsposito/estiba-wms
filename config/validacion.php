<?php

return [
    /*
    | Lote de materia prima y proceso de packing obligatorios en cada línea de la
    | composición al validar un pallet (salvo rechazos). Se activa después de instalar
    | en las PDA el APK que los solicita: las versiones anteriores no los envían.
    */
    'exigir_lote_proceso' => filter_var(
        env('WMS_VALIDACION_EXIGE_LOTE_PROCESO', false),
        FILTER_VALIDATE_BOOL,
    ),
];
