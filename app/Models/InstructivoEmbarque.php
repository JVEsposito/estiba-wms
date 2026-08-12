<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'embarque_id', 'orden', 'numero_externo', 'recibidor', 'destino_pais',
    'destino_ciudad', 'cantidad_pallets', 'cantidad_cajas', 'booking', 'sps',
    'dus', 'planilla_sag', 'sello_sag', 'observacion',
])]
class InstructivoEmbarque extends Model
{
    use HasUuids;

    protected $table = 'instructivos_embarque';

    public function embarque(): BelongsTo
    {
        return $this->belongsTo(Embarque::class);
    }

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cantidad_pallets' => 'integer',
            'cantidad_cajas' => 'integer',
        ];
    }
}
