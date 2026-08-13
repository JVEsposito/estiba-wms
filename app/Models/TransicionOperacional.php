<?php

namespace App\Models;

use App\Enums\DominioTransicionOperacional;
use App\Enums\EstadoTransicionOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'dominio',
    'tipo',
    'operacion_id',
    'estado',
    'sujeto_tipo',
    'sujeto_id',
    'referencia',
    'user_id',
    'dispositivo_id',
    'payload_hash',
    'payload',
    'resultado',
    'error_tipo',
    'error_codigo',
    'error_mensaje',
    'cantidad_cambios',
    'ocurrido_at',
    'finalizado_at',
])]
class TransicionOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'transiciones_operacionales';

    protected static function booted(): void
    {
        static::updating(function (TransicionOperacional $transicion): void {
            $estadoOriginal = EstadoTransicionOperacional::tryFrom(
                (string) $transicion->getRawOriginal('estado'),
            );

            if ($estadoOriginal?->esFinal()) {
                throw new DomainException(
                    'Una transición operacional finalizada no admite modificaciones.',
                );
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioTransicionOperacional::class)
            ->orderBy('secuencia');
    }

    protected function casts(): array
    {
        return [
            'dominio' => DominioTransicionOperacional::class,
            'estado' => EstadoTransicionOperacional::class,
            'payload' => 'array',
            'resultado' => 'array',
            'cantidad_cambios' => 'integer',
            'ocurrido_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }
}
