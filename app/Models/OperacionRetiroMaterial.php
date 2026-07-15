<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'despacho_material_id',
    'user_id',
    'dispositivo_id',
    'payload_hash',
    'procesada_at',
])]
class OperacionRetiroMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'operaciones_retiro_materiales';

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(DespachoMaterial::class, 'despacho_material_id');
    }

    public function retiros(): HasMany
    {
        return $this->hasMany(RetiroMaterial::class, 'operacion_retiro_material_id');
    }

    protected function casts(): array
    {
        return ['procesada_at' => 'datetime'];
    }
}
