<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'codigo',
    'nombre',
    'codigo_externo',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class ProveedorMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'proveedores_materiales';

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Cliente::class,
            'clientes_proveedores_materiales',
            'proveedor_material_id',
            'cliente_id',
        )
            ->withPivot(['id', 'activo', 'creado_por_user_id', 'actualizado_por_user_id'])
            ->withTimestamps();
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
