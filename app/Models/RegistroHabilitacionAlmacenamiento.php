<?php

namespace App\Models;

use App\Enums\CondicionTermicaFolio;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'folio_id',
    'estado_resultante',
    'condicion_termica',
    'fuente',
    'proceso_origen',
    'referencia_origen',
    'user_id',
    'dispositivo_id',
    'ocurrido_at',
    'motivo',
    'observacion',
])]
class RegistroHabilitacionAlmacenamiento extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public const UPDATED_AT = null;

    protected $table = 'historial_habilitaciones_almacenamiento';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException(
                'El historial de habilitaciones es inmutable y no admite modificaciones.',
            );
        });
    }

    protected function casts(): array
    {
        return [
            'estado_resultante' => HabilitacionAlmacenamientoFolio::class,
            'condicion_termica' => CondicionTermicaFolio::class,
            'fuente' => FuenteHabilitacionAlmacenamiento::class,
            'ocurrido_at' => 'datetime',
        ];
    }
}
