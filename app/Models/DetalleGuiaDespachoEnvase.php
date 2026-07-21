<?php

namespace App\Models;

use App\Enums\PropiedadEnvase;
use App\Enums\TipoEnvaseRomana;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['guia_despacho_envase_id', 'tipo_envase', 'cantidad', 'propiedad', 'movimiento_origen_id', 'origen_snapshot'])]
class DetalleGuiaDespachoEnvase extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'detalles_guias_despacho_envases';

    public function guia(): BelongsTo { return $this->belongsTo(GuiaDespachoEnvase::class, 'guia_despacho_envase_id'); }
    public function movimientoOrigen(): BelongsTo { return $this->belongsTo(MovimientoEnvase::class, 'movimiento_origen_id'); }

    protected function casts(): array
    {
        return [
            'tipo_envase' => TipoEnvaseRomana::class,
            'propiedad' => PropiedadEnvase::class,
            'cantidad' => 'integer',
        ];
    }
}
