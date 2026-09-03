<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'codigo',
    'nombre',
    'codigo_externo',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class Anden extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'andenes';

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_user_id');
    }

    public function cargasPrevistas(): HasMany
    {
        return $this->hasMany(Carga::class, 'anden_previsto_id');
    }

    public function presenciasCarga(): HasMany
    {
        return $this->hasMany(PresenciaCargaAnden::class);
    }

    public function presenciaActiva(): HasOne
    {
        return $this->hasOne(PresenciaCargaAnden::class, 'bloqueo_anden_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
