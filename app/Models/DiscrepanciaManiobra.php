<?php

namespace App\Models;

use App\Enums\EstadoDiscrepanciaManiobra;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'maniobra_operacional_id',
    'tarea_movimiento_id',
    'folio_id',
    'tipo',
    'detalle',
    'estado',
    'reportada_por_user_id',
    'dispositivo_id',
    'reportada_at',
    'resuelta_por_user_id',
    'resuelta_at',
    'resolucion',
])]
class DiscrepanciaManiobra extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'discrepancias_maniobra';

    public function maniobraOperacional(): BelongsTo
    {
        return $this->belongsTo(ManiobraOperacional::class);
    }

    public function tareaMovimiento(): BelongsTo
    {
        return $this->belongsTo(TareaMovimiento::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoDiscrepanciaManiobra::class,
            'reportada_at' => 'datetime',
            'resuelta_at' => 'datetime',
        ];
    }
}
