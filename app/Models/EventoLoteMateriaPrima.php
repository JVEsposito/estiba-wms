<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_materia_prima_id',
    'operacion_id',
    'tipo',
    'estado_anterior',
    'estado_nuevo',
    'user_id',
    'ocurrido_at',
    'datos',
])]
class EventoLoteMateriaPrima extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_lote_materia_prima';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class, 'lote_materia_prima_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['ocurrido_at' => 'datetime', 'datos' => 'array'];
    }
}
