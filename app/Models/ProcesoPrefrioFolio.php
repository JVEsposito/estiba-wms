<?php

namespace App\Models;

use App\Enums\EstadoFolioProcesoPrefrio;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'proceso_prefrio_id',
    'folio_id',
    'posicion_tunel_prefrio_id',
    'estado',
    'temperatura_inicial',
    'temperatura_final',
    'cargado_at',
    'retirado_at',
    'motivo_resultado',
    'observacion',
    'cargado_por_user_id',
    'retirado_por_user_id',
])]
class ProcesoPrefrioFolio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'procesos_prefrio_folios';

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoPrefrio::class, 'proceso_prefrio_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(PosicionTunelPrefrio::class, 'posicion_tunel_prefrio_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoPrefrio::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_user_id');
    }

    public function retiradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retirado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoFolioProcesoPrefrio::class,
            'temperatura_inicial' => 'decimal:2',
            'temperatura_final' => 'decimal:2',
            'cargado_at' => 'datetime',
            'retirado_at' => 'datetime',
        ];
    }
}
