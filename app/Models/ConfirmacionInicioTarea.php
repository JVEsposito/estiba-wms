<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tarea_movimiento_id',
    'folio_id',
    'numero_folio',
    'user_id',
    'dispositivo_id',
    'resultado',
    'digitos_ingresados',
    'confirmado_at',
])]
class ConfirmacionInicioTarea extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public const CONFIRMADA = 'confirmada';

    public const RECHAZADA_FOLIO = 'rechazada_folio';

    public const RECHAZADA_PIN = 'rechazada_pin';

    protected $table = 'confirmaciones_inicio_tarea';

    public function tareaMovimiento(): BelongsTo
    {
        return $this->belongsTo(TareaMovimiento::class);
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
            'confirmado_at' => 'datetime',
        ];
    }
}
