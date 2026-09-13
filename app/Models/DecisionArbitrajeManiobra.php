<?php

namespace App\Models;

use App\Enums\DecisionArbitrajeManiobra as DecisionArbitraje;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ciclo_arbitraje_id',
    'maniobra_operacional_id',
    'orden',
    'decision',
    'puntaje',
    'beneficio_neto',
    'motivo',
    'conflictos',
])]
class DecisionArbitrajeManiobra extends Model
{
    use ImpideEliminacionFisica;

    protected $table = 'decisiones_arbitraje_maniobras';

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloArbitrajeManiobras::class, 'ciclo_arbitraje_id');
    }

    public function maniobraOperacional(): BelongsTo
    {
        return $this->belongsTo(ManiobraOperacional::class);
    }

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'decision' => DecisionArbitraje::class,
            'puntaje' => 'integer',
            'beneficio_neto' => 'integer',
            'conflictos' => 'array',
        ];
    }
}
