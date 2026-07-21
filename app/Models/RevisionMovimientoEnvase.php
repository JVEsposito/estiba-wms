<?php

namespace App\Models;

use App\Enums\EstadoRevisionMovimientoEnvase;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['movimiento_envase_id', 'estado', 'nota', 'user_id', 'revisado_at'])]
class RevisionMovimientoEnvase extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'revisiones_movimientos_envases';

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoEnvase::class, 'movimiento_envase_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoRevisionMovimientoEnvase::class,
            'revisado_at' => 'datetime',
        ];
    }
}
