<?php

namespace App\Models;

use App\Enums\EstadoValidacionPallet;
use App\Enums\MotivoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'operacion_id', 'payload_hash', 'numero_folio', 'numero_intento',
    'tipo_bulto', 'cantidad_cajas', 'temporada_id', 'articulo_validacion_id',
    'origen_validacion_id', 'categoria_validacion_id', 'resultado', 'estado', 'motivo', 'observacion',
    'catalogo_version_dispositivo', 'catalogo_version_servidor', 'snapshot',
    'user_id', 'dispositivo_id', 'folio_id', 'validacion_conflicto_id',
    'generado_dispositivo_at', 'recibido_servidor_at',
])]
class ValidacionPallet extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'validaciones_pallet';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaValidacion::class, 'categoria_validacion_id');
    }

    public function conflictoCon(): BelongsTo
    {
        return $this->belongsTo(self::class, 'validacion_conflicto_id');
    }

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoValidacionPallet::class,
            'estado' => EstadoValidacionPallet::class,
            'motivo' => MotivoValidacionPallet::class,
            'snapshot' => 'array',
            'generado_dispositivo_at' => 'datetime',
            'recibido_servidor_at' => 'datetime',
        ];
    }
}
