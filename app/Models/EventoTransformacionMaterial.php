<?php

namespace App\Models;

use App\Enums\TipoEventoTransformacionMaterial;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'orden_transformacion_material_id',
    'operacion_id',
    'tipo',
    'datos',
    'observacion',
    'user_id',
    'dispositivo_id',
    'ocurrido_at',
])]
class EventoTransformacionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_transformacion_materiales';

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTransformacionMaterial::class, 'orden_transformacion_material_id');
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
            'tipo' => TipoEventoTransformacionMaterial::class,
            'datos' => 'array',
            'ocurrido_at' => 'datetime',
        ];
    }
}
