<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'proveedor_material_id',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class ClienteProveedorMaterial extends Model
{
    use HasUuids;

    protected $table = 'clientes_proveedores_materiales';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(ProveedorMaterial::class, 'proveedor_material_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
