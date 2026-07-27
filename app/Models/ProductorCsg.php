<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'rut',
    'razon_social',
    'predio',
    'direccion',
    'estado_sag',
    'tipo_codigo',
    'especies',
    'fuente_url',
    'primera_verificacion_at',
    'ultima_verificacion_at',
    'ultima_consulta_user_id',
    'respuesta_hash',
    'datos_fuente',
])]
class ProductorCsg extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'productores_csg';

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Cliente::class,
            'clientes_productores_csg',
            'productor_csg_id',
            'cliente_id',
        )
            ->withPivot(['id', 'activo', 'asociado_por_user_id', 'actualizado_por_user_id'])
            ->withTimestamps();
    }

    public function catalogosTemporada(): HasMany
    {
        return $this->hasMany(CsgValidacion::class, 'productor_csg_id');
    }

    public function ultimaConsultaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultima_consulta_user_id');
    }

    public function getEstadoAsociacionAttribute(): string
    {
        $asociado = $this->relationLoaded('clientes')
            ? $this->clientes->contains(fn (Cliente $cliente): bool => (bool) $cliente->pivot->activo)
            : $this->clientes()->wherePivot('activo', true)->exists();

        return $asociado ? 'asociado' : 'pendiente_cliente';
    }

    protected function casts(): array
    {
        return [
            'especies' => 'array',
            'datos_fuente' => 'array',
            'primera_verificacion_at' => 'datetime',
            'ultima_verificacion_at' => 'datetime',
        ];
    }
}
