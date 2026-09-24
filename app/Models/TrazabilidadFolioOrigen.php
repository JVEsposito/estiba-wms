<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proyección de la composición de un folio: CSG, lote de materia prima, proceso de
 * packing y cajas. Se reconstruye desde folios.datos_externos cada vez que cambia.
 */
#[Fillable([
    'folio_id',
    'temporada_id',
    'csg',
    'predio',
    'fecha_embalaje',
    'numero_lote_materia_prima',
    'lote_materia_prima_id',
    'numero_proceso_packing',
    'cantidad_cajas',
])]
class TrazabilidadFolioOrigen extends Model
{
    use HasUuids;

    protected $table = 'trazabilidad_folio_origenes';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function loteMateriaPrima(): BelongsTo
    {
        return $this->belongsTo(LoteMateriaPrima::class);
    }

    protected function casts(): array
    {
        return [
            'fecha_embalaje' => 'date:Y-m-d',
            'cantidad_cajas' => 'integer',
        ];
    }
}
