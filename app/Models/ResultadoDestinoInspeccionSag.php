<?php

namespace App\Models;

use App\Enums\ResultadoInspeccionSag;
use App\Enums\TipoAprobacionSag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_inspeccion_sag_folio_id', 'destino_lote_inspeccion_sag_id', 'resultado',
    'tipo_aprobacion', 'observacion', 'resuelto_por_user_id', 'resuelto_at',
])]
class ResultadoDestinoInspeccionSag extends Model
{
    use HasUuids;

    protected $table = 'resultados_destino_inspeccion_sag';

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(LoteInspeccionSagFolio::class, 'lote_inspeccion_sag_folio_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(DestinoLoteInspeccionSag::class, 'destino_lote_inspeccion_sag_id');
    }

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoInspeccionSag::class,
            'tipo_aprobacion' => TipoAprobacionSag::class,
            'resuelto_at' => 'datetime',
        ];
    }
}
