<?php

namespace App\Models;

use App\Enums\EstadoTareaCarga;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carga_id',
    'camara_origen_id',
    'responsable_user_id',
    'estado',
    'asumida_at',
    'completada_at',
])]
class TareaCarga extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'tareas_carga';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function camaraOrigen(): BelongsTo
    {
        return $this->belongsTo(Camara::class, 'camara_origen_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoTareaCarga::class,
            'asumida_at' => 'datetime',
            'completada_at' => 'datetime',
        ];
    }
}
