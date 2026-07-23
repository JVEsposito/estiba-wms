<?php

namespace App\Models;

use App\Enums\TipoEventoRecepcionMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recepcion_material_id',
    'operacion_id',
    'tipo',
    'datos',
    'observacion',
    'user_id',
    'ocurrido_at',
])]
class EventoRecepcionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_recepciones_materiales';

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionMaterial::class, 'recepcion_material_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoRecepcionMaterial::class,
            'datos' => 'array',
            'ocurrido_at' => 'datetime',
        ];
    }
}
