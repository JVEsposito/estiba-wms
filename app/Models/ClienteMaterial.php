<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'temporada_material_id',
    'codigo',
    'nombre',
    'codigo_externo',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class ClienteMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'clientes_materiales';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(TemporadaMaterial::class, 'temporada_material_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemMaterial::class, 'cliente_material_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_user_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
