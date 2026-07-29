<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'operacion_id',
    'recepcion_material_id_original',
    'temporada_id',
    'cliente_id',
    'proveedor_material_id',
    'numero_guia_despacho',
    'motivo',
    'folios',
    'snapshot',
    'eliminado_por_user_id',
    'eliminado_at',
])]
class EliminacionRecepcionMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eliminaciones_recepciones_materiales';

    protected function casts(): array
    {
        return [
            'folios' => 'array',
            'snapshot' => 'array',
            'eliminado_at' => 'datetime',
        ];
    }
}
