<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id', 'despacho_material_id', 'asignado_a_user_id', 'asignado_por_user_id', 'motivo',
])]
class AsignacionDespachoMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'asignaciones_despachos_materiales';

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a_user_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_user_id');
    }
}
