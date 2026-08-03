<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nombre',
    'prefijo_sublote',
    'activo',
    'orden',
])]
class TipoResultadoPacking extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'tipos_resultado_packing';

    public function sublotes(): HasMany
    {
        return $this->hasMany(SubloteRetornoPacking::class, 'tipo_resultado_packing_id');
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
