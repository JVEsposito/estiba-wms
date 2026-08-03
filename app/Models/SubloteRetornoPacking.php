<?php

namespace App\Models;

use App\Enums\EstadoSubloteRetornoPacking;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'retorno_packing_id',
    'tipo_resultado_packing_id',
    'numero_sublote',
    'nombre_resultado',
    'cantidad_bins',
    'kilos_netos',
    'estado',
    'camara_id',
    'operacion_ubicacion_id',
    'ubicado_por_user_id',
    'dispositivo_ubicacion_id',
    'ubicado_at',
    'observacion_ubicacion',
])]
class SubloteRetornoPacking extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'sublotes_retorno_packing';

    public function retorno(): BelongsTo
    {
        return $this->belongsTo(RetornoPacking::class, 'retorno_packing_id');
    }

    public function tipoResultado(): BelongsTo
    {
        return $this->belongsTo(TipoResultadoPacking::class, 'tipo_resultado_packing_id');
    }

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function ubicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ubicado_por_user_id');
    }

    public function dispositivoUbicacion(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class, 'dispositivo_ubicacion_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad_bins' => 'integer',
            'kilos_netos' => 'decimal:3',
            'estado' => EstadoSubloteRetornoPacking::class,
            'ubicado_at' => 'datetime',
        ];
    }
}
