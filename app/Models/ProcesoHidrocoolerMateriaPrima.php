<?php

namespace App\Models;

use App\Enums\EstadoHidrocoolerMateriaPrima;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo',
    'lote_materia_prima_id',
    'operacion_inicio_id',
    'payload_inicio_hash',
    'operacion_termino_id',
    'payload_termino_hash',
    'estado',
    'equipo',
    'equipo_activo_clave',
    'operador_snapshot',
    'cantidad_envases_snapshot',
    'kilos_netos_snapshot',
    'inicio_at',
    'termino_at',
    'duracion_minutos',
    'temperatura_inicial_c',
    'temperatura_objetivo_c',
    'temperatura_agua_inicial_c',
    'temperatura_c',
    'temperatura_agua_final_c',
    'destino_salida',
    'observacion_inicio',
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
            'cantidad_envases_snapshot' => 'integer',
            'kilos_netos_snapshot' => 'decimal:3',
            'temperatura_inicial_c' => 'decimal:2',
            'temperatura_objetivo_c' => 'decimal:2',
            'temperatura_agua_inicial_c' => 'decimal:2',
            'temperatura_c' => 'decimal:2',
            'temperatura_agua_final_c' => 'decimal:2',
        ];
    }
}
