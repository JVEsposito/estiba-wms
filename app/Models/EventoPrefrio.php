<?php

namespace App\Models;

use App\Enums\TipoEventoPrefrio;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'proceso_prefrio_id',
    'proceso_prefrio_folio_id',
    'tipo',
    'user_id',
    'dispositivo_id',
    'ocurrido_at',
    'datos',
    'observacion',
])]
class EventoPrefrio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_prefrio';

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoPrefrio::class, 'proceso_prefrio_id');
    }

    public function asignacionFolio(): BelongsTo
    {
        return $this->belongsTo(ProcesoPrefrioFolio::class, 'proceso_prefrio_folio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoPrefrio::class,
            'ocurrido_at' => 'datetime',
            'datos' => 'array',
        ];
    }
}
