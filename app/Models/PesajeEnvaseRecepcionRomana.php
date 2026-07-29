<?php

namespace App\Models;

use App\Enums\TipoEnvaseRomana;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'recepcion_romana_id',
    'secuencia',
    'tipo_envase',
    'cantidad_envases',
    'peso_bruto',
    'tara_unitaria_envase',
    'peso_tara',
    'peso_neto',
    'observacion',
    'registrado_por_user_id',
    'pesado_at',
    'operacion_anulacion_id',
    'payload_anulacion_hash',
    'anulado_at',
    'anulado_por_user_id',
    'motivo_anulacion',
])]
class PesajeEnvaseRecepcionRomana extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'pesajes_envases_recepcion_romana';

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionRomana::class, 'recepcion_romana_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_envase' => TipoEnvaseRomana::class,
            'cantidad_envases' => 'integer',
            'peso_bruto' => 'decimal:3',
            'tara_unitaria_envase' => 'decimal:3',
            'peso_tara' => 'decimal:3',
            'peso_neto' => 'decimal:3',
            'pesado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
