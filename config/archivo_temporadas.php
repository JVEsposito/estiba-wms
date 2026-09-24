<?php

return [
    // Disco donde se guardan los paquetes (config/filesystems.php).
    'disco' => env('ARCHIVO_TEMPORADAS_DISK', 'archivo_temporadas'),

    // Días que la temporada debe llevar cerrada (desde fecha_fin) antes de poder archivarse.
    'dias_minimos_cierre' => (int) env('ARCHIVO_TEMPORADAS_DIAS_CIERRE', 60),

    // Tablas técnicas que no forman parte de la operación de una temporada.
    'tablas_excluidas' => [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'archivos_temporada',
        'archivo_temporada_claves',
    ],

    // Tablas que además se entregan como Excel de líneas limpias (formato base de datos).
    'tablas_excel' => [
        'folios',
        'validaciones_pallet',
        'trazabilidad_folio_origenes',
        'cargas',
        'carga_folios',
        'movimientos',
        'recepciones_romana',
        'lotes_materia_prima',
        'entregas_fruta_proceso',
    ],
];
