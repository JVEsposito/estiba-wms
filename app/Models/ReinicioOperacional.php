<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'temporada_id',
    'alcances',
    'motivo',
    'resumen_antes',
    'resumen_eliminado',
    'resumen_despues',
    'ejecutado_por_user_id',
])]
class ReinicioOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'reinicios_operacionales';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'alcances' => 'array',
            'resumen_antes' => 'array',
            'resumen_eliminado' => 'array',
            'resumen_despues' => 'array',
        ];
    }
}
