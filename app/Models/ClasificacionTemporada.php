<?php

namespace App\Models;

use App\Enums\TipoTemporada;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'temporada_id',
    'tipo_anterior',
    'tipo_nuevo',
    'motivo',
    'clasificado_por_user_id',
    'clasificado_at',
])]
class ClasificacionTemporada extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'clasificaciones_temporada';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('El historial de clasificación de temporadas es inmutable.');
        });
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function clasificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clasificado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_anterior' => TipoTemporada::class,
            'tipo_nuevo' => TipoTemporada::class,
            'clasificado_at' => 'datetime',
        ];
    }
}
