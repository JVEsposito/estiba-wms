<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'guia_despacho_envase_id',
    'tipo',
    'estado_anterior',
    'estado_nuevo',
    'user_id',
    'ocurrido_at',
    'datos',
])]
class EventoGuiaDespachoEnvase extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_guias_despacho_envases';

    public function guia(): BelongsTo
    {
        return $this->belongsTo(GuiaDespachoEnvase::class, 'guia_despacho_envase_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
            'datos' => 'array',
        ];
    }
}
