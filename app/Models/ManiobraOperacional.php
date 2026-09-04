<?php

namespace App\Models;

use App\Enums\EstadoManiobraOperacional;
use App\Enums\PrioridadOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plan_operacional_id',
    'creado_por_user_id',
    'estado',
    'prioridad',
    'candidate_key',
    'titulo',
    'motivo',
    'secuencia_actual',
    'costo_movimientos',
    'beneficio_estimado',
    'riesgo_operacional',
    'contexto',
    'responsable_user_id',
    'dispositivo_id',
    'asumida_at',
    'iniciada_at',
    'pausada_at',
    'completada_at',
    'cancelada_at',
    'motivo_cancelacion',
    'version',
])]
class ManiobraOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'maniobras_operacionales';

    public function planOperacional(): BelongsTo
    {
        return $this->belongsTo(PlanOperacional::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function objetivos(): BelongsToMany
    {
        return $this->belongsToMany(
            PlanOperacional::class,
            'maniobra_objetivos',
            'maniobra_operacional_id',
            'plan_operacional_id',
        )->withPivot(['es_principal', 'beneficio_estimado', 'contexto'])->withTimestamps();
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(TareaMovimiento::class)->orderBy('secuencia_maniobra');
    }

    public function custodiasTemporales(): HasMany
    {
        return $this->hasMany(CustodiaTemporalManiobra::class);
    }

    public function reservasBandas(): HasMany
    {
        return $this->hasMany(ReservaBandaManiobra::class);
    }

    public function discrepancias(): HasMany
    {
        return $this->hasMany(DiscrepanciaManiobra::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoManiobraOperacional::class,
            'prioridad' => PrioridadOperacional::class,
            'secuencia_actual' => 'integer',
            'costo_movimientos' => 'integer',
            'beneficio_estimado' => 'integer',
            'riesgo_operacional' => 'integer',
            'contexto' => 'array',
            'asumida_at' => 'datetime',
            'iniciada_at' => 'datetime',
            'pausada_at' => 'datetime',
            'completada_at' => 'datetime',
            'cancelada_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
