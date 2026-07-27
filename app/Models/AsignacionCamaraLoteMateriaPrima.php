<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'lote_materia_prima_id',
    'camara_id',
    'asignado_por_user_id',
    'asignado_at',
    'observacion',
])]
class AsignacionCamaraLoteMateriaPrima extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'asignaciones_camara_lote_materia_prima';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class, 'lote_materia_prima_id');
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_user_id');
    }

    protected function casts(): array
    {
        return ['asignado_at' => 'datetime'];
    }
}
