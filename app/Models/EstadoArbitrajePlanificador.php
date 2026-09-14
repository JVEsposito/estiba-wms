<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'temporada_id',
    'ultimo_ciclo_id',
    'version_solicitada',
    'version_calculada',
    'calculos_exitosos',
    'calculos_fallidos',
    'espera_ultimo_calculo_ms',
    'duracion_ultimo_calculo_ms',
    'solicitado_at',
    'iniciado_at',
    'calculado_at',
    'fallo_at',
    'ultimo_motivo',
    'ultimo_error',
])]
class EstadoArbitrajePlanificador extends Model
{
    protected $table = 'estados_arbitraje_planificador';

    protected $primaryKey = 'temporada_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function ultimoCiclo(): BelongsTo
    {
        return $this->belongsTo(CicloArbitrajeManiobras::class, 'ultimo_ciclo_id');
    }

    protected function casts(): array
    {
        return [
            'version_solicitada' => 'integer',
            'version_calculada' => 'integer',
            'calculos_exitosos' => 'integer',
            'calculos_fallidos' => 'integer',
            'espera_ultimo_calculo_ms' => 'integer',
            'duracion_ultimo_calculo_ms' => 'integer',
            'solicitado_at' => 'datetime',
            'iniciado_at' => 'datetime',
            'calculado_at' => 'datetime',
            'fallo_at' => 'datetime',
        ];
    }
}
