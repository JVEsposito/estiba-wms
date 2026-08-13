<?php

namespace App\Models;

use App\Enums\TipoCambioTransicionOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transicion_operacional_id',
    'secuencia',
    'modelo_tipo',
    'modelo_id',
    'tipo',
    'campos',
    'datos_anteriores',
    'datos_nuevos',
])]
class CambioTransicionOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public const UPDATED_AT = null;

    protected $table = 'cambios_transiciones_operacionales';

    protected static function booted(): void
    {
        static::updating(function (CambioTransicionOperacional $cambio): never {
            throw new DomainException(
                sprintf(
                    'El cambio operacional %s no admite modificaciones.',
                    $cambio->id,
                ),
            );
        });
    }

    public function transicion(): BelongsTo
    {
        return $this->belongsTo(
            TransicionOperacional::class,
            'transicion_operacional_id',
        );
    }

    protected function casts(): array
    {
        return [
            'secuencia' => 'integer',
            'tipo' => TipoCambioTransicionOperacional::class,
            'campos' => 'array',
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
        ];
    }
}
