<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nombre_archivo',
    'tipo_archivo',
    'checksum',
    'estado',
    'resumen',
    'filas',
    'errores',
    'creado_por_user_id',
    'confirmado_por_user_id',
    'confirmado_at',
])]
class ImportacionCatalogoMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'importaciones_catalogo_materiales';

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'resumen' => 'array',
            'filas' => 'array',
            'errores' => 'array',
            'confirmado_at' => 'datetime',
        ];
    }
}
