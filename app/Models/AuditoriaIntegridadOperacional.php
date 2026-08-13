<?php

namespace App\Models;

use App\Enums\EstadoAuditoriaIntegridadOperacional;
use App\Enums\OrigenAuditoriaIntegridadOperacional;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'origen',
    'estado',
    'iniciada_por_user_id',
    'iniciada_at',
    'finalizada_at',
    'duracion_ms',
    'hallazgos_activos',
    'hallazgos_criticos',
    'hallazgos_advertencia',
    'hallazgos_informativos',
    'hallazgos_nuevos',
    'hallazgos_resueltos',
    'reglas_ejecutadas',
    'error',
])]
class AuditoriaIntegridadOperacional extends Model
{
    use HasUuids;

    protected $table = 'auditorias_integridad';

    public function iniciadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciada_por_user_id');
    }

    public function hallazgosDetectados(): HasMany
    {
        return $this->hasMany(
            HallazgoIntegridadOperacional::class,
            'ultima_auditoria_id',
        );
    }

    protected function casts(): array
    {
        return [
            'origen' => OrigenAuditoriaIntegridadOperacional::class,
            'estado' => EstadoAuditoriaIntegridadOperacional::class,
            'iniciada_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'duracion_ms' => 'integer',
            'hallazgos_activos' => 'integer',
            'hallazgos_criticos' => 'integer',
            'hallazgos_advertencia' => 'integer',
            'hallazgos_informativos' => 'integer',
            'hallazgos_nuevos' => 'integer',
            'hallazgos_resueltos' => 'integer',
            'reglas_ejecutadas' => 'array',
        ];
    }
}
