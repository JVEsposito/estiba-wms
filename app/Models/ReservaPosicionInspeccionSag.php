<?php

namespace App\Models;

use App\Enums\TipoEspacioPreparacionSag;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_inspeccion_sag_id',
    'plan_operacional_id',
    'posicion_id',
    'tipo_espacio',
    'orden',
    'clave_bloqueo',
    'reservada_at',
    'liberada_at',
    'motivo_liberacion',
])]
class ReservaPosicionInspeccionSag extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'reservas_posiciones_inspeccion_sag';

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInspeccionSag::class, 'lote_inspeccion_sag_id');
    }

    public function planOperacional(): BelongsTo
    {
        return $this->belongsTo(PlanOperacional::class);
    }

    public function posicion(): BelongsTo
    {
        return $this->belongsTo(Posicion::class);
    }

    protected function casts(): array
    {
        return [
            'tipo_espacio' => TipoEspacioPreparacionSag::class,
            'orden' => 'integer',
            'reservada_at' => 'datetime',
            'liberada_at' => 'datetime',
        ];
    }
}
