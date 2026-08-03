<?php

namespace App\Models;

use App\Enums\TipoAlmacenMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'codigo',
    'nombre',
    'tipo',
    'centro_costo',
    'requiere_ubicacion_fisica',
    'descripcion',
    'codigo_externo',
    'origen_sistema',
    'sincronizado_at',
    'activo',
    'creado_por_user_id',
    'actualizado_por_user_id',
])]
class DestinoMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'destinos_materiales';

    protected static function booted(): void
    {
        static::creating(function (DestinoMaterial $destino): void {
            if (! $destino->codigo) {
                $destino->codigo = 'ALM-'.Str::upper(
                    substr(str_replace('-', '', (string) Str::uuid()), 0, 8),
                );
            }

            $destino->tipo ??= TipoAlmacenMaterial::Virtual->value;
            $destino->requiere_ubicacion_fisica ??= false;
        });
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(DespachoMaterial::class, 'destino_material_id');
    }

    public function saldos(): HasMany
    {
        return $this->hasMany(SaldoMaterialAlmacen::class, 'almacen_material_id');
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
            'requiere_ubicacion_fisica' => 'boolean',
            'activo' => 'boolean',
            'sincronizado_at' => 'datetime',
        ];
    }
}
