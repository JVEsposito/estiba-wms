<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipo_busqueda',
    'valor_normalizado',
    'estado',
    'cantidad_resultados',
    'duracion_ms',
    'error',
    'user_id',
    'ocurrido_at',
])]
class ConsultaSag extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'consultas_sag';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_resultados' => 'integer',
            'duracion_ms' => 'integer',
            'ocurrido_at' => 'datetime',
        ];
    }
}
