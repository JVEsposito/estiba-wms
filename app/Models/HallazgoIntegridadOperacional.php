<?php

namespace App\Models;

use App\Enums\SeveridadHallazgoIntegridadOperacional;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'huella',
    'regla_codigo',
    'severidad',
    'modulo',
    'entidad_tipo',
    'entidad_id',
    'referencia',
    'titulo',
    'detalle',
    'contexto',
    'activo',
    'ocurrencias',
    'primera_auditoria_id',
    'ultima_auditoria_id',
    'detectado_primera_vez_at',
    'detectado_ultima_vez_at',
    'resuelto_at',
])]
class HallazgoIntegridadOperacional extends Model
{
    use HasUuids;

    protected $table = 'hallazgos_integridad';

    public function primeraAuditoria(): BelongsTo
    {
        return $this->belongsTo(
            AuditoriaIntegridadOperacional::class,
            'primera_auditoria_id',
        );
    }

    public function ultimaAuditoria(): BelongsTo
    {
        return $this->belongsTo(
            AuditoriaIntegridadOperacional::class,
            'ultima_auditoria_id',
        );
    }

    protected function casts(): array
    {
        return [
            'severidad' => SeveridadHallazgoIntegridadOperacional::class,
            'contexto' => 'array',
            'activo' => 'boolean',
            'ocurrencias' => 'integer',
            'detectado_primera_vez_at' => 'datetime',
            'detectado_ultima_vez_at' => 'datetime',
            'resuelto_at' => 'datetime',
        ];
    }
}
