<?php

namespace App\Models;

use App\Enums\EstadoIncidenciaCarga;
use App\Enums\TipoIncidenciaCarga;
use App\Enums\TipoResolucionIncidenciaCarga;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_reporte_id',
    'reporte_payload_hash',
    'carga_folio_id',
    'tipo',
    'descripcion',
    'estado',
    'camara_id',
    'posicion_id',
    'reportado_por_user_id',
    'dispositivo_id',
    'sesion_estiba_id',
    'reportada_at',
    'operacion_resolucion_id',
    'resolucion_payload_hash',
    'tipo_resolucion',
    'observacion_resolucion',
    'resuelta_por_user_id',
    'resuelta_at',
    'carga_folio_reemplazo_id',
])]
class IncidenciaCargaFolio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'incidencias_carga_folio';

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(CargaFolio::class, 'carga_folio_id');
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(Posicion::class);
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function sesionEstiba(): BelongsTo
    {
        return $this->belongsTo(SesionEstiba::class);
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por_user_id');
    }

    public function asignacionReemplazo(): BelongsTo
    {
        return $this->belongsTo(CargaFolio::class, 'carga_folio_reemplazo_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoIncidenciaCarga::class,
            'estado' => EstadoIncidenciaCarga::class,
            'tipo_resolucion' => TipoResolucionIncidenciaCarga::class,
            'reportada_at' => 'datetime',
            'resuelta_at' => 'datetime',
        ];
    }
}
