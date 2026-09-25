<?php

namespace App\Models;

use App\Enums\CategoriaPendienteCierre;
use App\Enums\MotivoRegularizacionCierre;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'temporada_id',
    'lote_regularizacion_id',
    'categoria',
    'entidad_id',
    'referencia',
    'estado_anterior',
    'motivo_categoria',
    'motivo',
    'snapshot',
    'regularizado_por_user_id',
    'regularizado_at',
])]
class RegularizacionCierreTemporada extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'regularizaciones_cierre_temporada';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('Las regularizaciones de cierre de temporada son inmutables.');
        });
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function regularizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularizado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaPendienteCierre::class,
            'motivo_categoria' => MotivoRegularizacionCierre::class,
            'snapshot' => 'array',
            'regularizado_at' => 'datetime',
        ];
    }
}
