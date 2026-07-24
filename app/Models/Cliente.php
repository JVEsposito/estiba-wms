<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'codigo',
    'nombre',
    'codigo_externo',
    'codigo_folio_materiales',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class Cliente extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public function catalogosValidacion(): HasMany
    {
        return $this->hasMany(ClienteValidacion::class);
    }

    public function catalogosMateriales(): HasMany
    {
        return $this->hasMany(ClienteMaterial::class);
    }

    public function recepcionesRomana(): HasMany
    {
        return $this->hasMany(RecepcionRomana::class);
    }

    public function recepcionesMateriales(): HasMany
    {
        return $this->hasMany(RecepcionMaterial::class);
    }

    public function correlativoMateriales(): HasOne
    {
        return $this->hasOne(CorrelativoMaterialCliente::class);
    }

    public function movimientosEnvases(): HasMany
    {
        return $this->hasMany(MovimientoEnvase::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(AliasCliente::class);
    }

    public function proveedoresMateriales(): BelongsToMany
    {
        return $this->belongsToMany(
            ProveedorMaterial::class,
            'clientes_proveedores_materiales',
            'cliente_id',
            'proveedor_material_id',
        )
            ->withPivot(['id', 'activo', 'categorias', 'creado_por_user_id', 'actualizado_por_user_id'])
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
