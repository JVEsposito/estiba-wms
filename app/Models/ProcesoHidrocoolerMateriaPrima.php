<?php

namespace App\Models;

use App\Enums\EstadoHidrocoolerMateriaPrima;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_materia_prima_id',
    'operacion_inicio_id',
    'operacion_termino_id',
    'estado',
    'equipo',
    'inicio_at',
    'termino_at',
    'duracion_minutos',
    'temperatura_c',
    'observacion',
    'iniciado_por_user_id',
    'completado_por_user_id',
])]
class ProcesoHidrocoolerMateriaPrima extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'procesos_hidrocooler_materia_prima';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class, 'lote_materia_prima_id');
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_user_id');
    }

    public function completadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoHidrocoolerMateriaPrima::class,
            'inicio_at' => 'datetime',
            'termino_at' => 'datetime',
            'duracion_minutos' => 'integer',
            'temperatura_c' => 'decimal:2',
        ];
    }
}
