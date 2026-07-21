<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'temporada_origen_id',
    'temporada_destino_id',
    'copio_catalogo_validacion',
    'copio_catalogo_materiales',
    'migro_inventario_materiales',
    'activo_destino',
    'resumen',
    'creado_por_user_id',
])]
class MigracionTemporada extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'migraciones_temporadas';

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Temporada::class, 'temporada_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Temporada::class, 'temporada_destino_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function folios(): HasMany
    {
        return $this->hasMany(MigracionTemporadaFolio::class);
    }

    protected function casts(): array
    {
        return [
            'copio_catalogo_validacion' => 'boolean',
            'copio_catalogo_materiales' => 'boolean',
            'migro_inventario_materiales' => 'boolean',
            'activo_destino' => 'boolean',
            'resumen' => 'array',
        ];
    }
}
