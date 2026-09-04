<?php

namespace App\Models;

use App\Enums\EstadoCustodiaTemporal;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'maniobra_operacional_id',
    'folio_id',
    'tarea_extraccion_id',
    'tarea_resolucion_id',
    'camara_origen_id',
    'posicion_origen_id',
    'banda_origen',
    'posicion_origen',
    'nivel_origen',
    'estado',
    'bloqueo_folio_id',
    'user_id',
    'dispositivo_id',
    'extraido_at',
    'resuelto_at',
    'contexto',
])]
class CustodiaTemporalManiobra extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'custodias_temporales_maniobra';

    public function maniobraOperacional(): BelongsTo
    {
        return $this->belongsTo(ManiobraOperacional::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function tareaExtraccion(): BelongsTo
    {
        return $this->belongsTo(TareaMovimiento::class, 'tarea_extraccion_id');
    }

    public function tareaResolucion(): BelongsTo
    {
        return $this->belongsTo(TareaMovimiento::class, 'tarea_resolucion_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoCustodiaTemporal::class,
            'banda_origen' => 'integer',
            'posicion_origen' => 'integer',
            'nivel_origen' => 'integer',
            'extraido_at' => 'datetime',
            'resuelto_at' => 'datetime',
            'contexto' => 'array',
        ];
    }
}
