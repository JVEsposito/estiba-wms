<?php

namespace App\Models;

use App\Enums\TipoAprobacionSag;
use App\Enums\TipoDestinoSag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'folio_id', 'tipo_aprobacion', 'tipo_destino', 'pais_id', 'bloque_mercado_id',
    'resultado_origen_id', 'destino_snapshot', 'miembros_snapshot', 'activa',
    'aprobado_por_user_id', 'aprobado_at', 'revocado_por_user_id', 'revocado_at',
    'motivo_revocacion',
])]
class AutorizacionSagFolio extends Model
{
    use HasUuids;

    protected $table = 'autorizaciones_sag_folio';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(BloqueMercado::class, 'bloque_mercado_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_aprobacion' => TipoAprobacionSag::class,
            'tipo_destino' => TipoDestinoSag::class,
            'destino_snapshot' => 'array',
            'miembros_snapshot' => 'array',
            'activa' => 'boolean',
            'aprobado_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }
}
