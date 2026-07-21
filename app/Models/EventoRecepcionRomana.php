<?php

namespace App\Models;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\TipoEventoRomana;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'recepcion_romana_id',
    'tipo',
    'estado_anterior',
    'estado_nuevo',
    'user_id',
    'ocurrido_at',
    'datos',
])]
class EventoRecepcionRomana extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_recepcion_romana';

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionRomana::class, 'recepcion_romana_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoRomana::class,
            'estado_anterior' => EstadoRecepcionRomana::class,
            'estado_nuevo' => EstadoRecepcionRomana::class,
            'ocurrido_at' => 'datetime',
            'datos' => 'array',
        ];
    }
}
