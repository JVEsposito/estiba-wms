<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nombre',
    'fabricante',
    'modelo',
    'lenguaje',
    'dpi',
    'ancho_mm',
    'alto_mm',
    'orientacion',
    'predeterminado',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class PerfilImpresionEtiqueta extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'perfiles_impresion_etiquetas';

    public function trabajos(): HasMany
    {
        return $this->hasMany(TrabajoImpresionMaterial::class, 'perfil_impresion_etiqueta_id');
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
        return [
            'dpi' => 'integer',
            'ancho_mm' => 'decimal:2',
            'alto_mm' => 'decimal:2',
            'predeterminado' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
