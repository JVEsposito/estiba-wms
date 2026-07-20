<?php

namespace App\Models;

use App\Enums\AudienciaNotificacionOperacional;
use App\Enums\SeveridadNotificacionOperacional;
use App\Enums\TipoNotificacionOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'clave',
    'tipo',
    'audiencia_tipo',
    'audiencia_valor',
    'severidad',
    'titulo',
    'mensaje',
    'carga_id',
    'despacho_material_id',
    'folio_id',
    'incidencia_carga_folio_id',
    'datos',
])]
class NotificacionOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'notificaciones_operacionales';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function despachoMaterial(): BelongsTo
    {
        return $this->belongsTo(DespachoMaterial::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(IncidenciaCargaFolio::class, 'incidencia_carga_folio_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(LecturaNotificacionOperacional::class);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacionOperacional::class,
            'audiencia_tipo' => AudienciaNotificacionOperacional::class,
            'severidad' => SeveridadNotificacionOperacional::class,
            'datos' => 'array',
        ];
    }
}
