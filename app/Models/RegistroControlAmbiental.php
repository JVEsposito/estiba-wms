<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'camara_id',
    'registrado_por_user_id',
    'dispositivo_id',
    'temperatura_inicio_c',
    'temperatura_medio_c',
    'temperatura_fondo_c',
    'capturado_at',
    'recibido_servidor_at',
    'version',
])]
class RegistroControlAmbiental extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public const FRECUENCIA_MINUTOS = 60;

    protected $table = 'registros_control_ambiental';

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(CorreccionControlAmbiental::class)
            ->orderBy('corregido_at');
    }

    public function vigenteHasta(): CarbonInterface
    {
        return $this->capturado_at->copy()->addMinutes(self::FRECUENCIA_MINUTOS);
    }

    public function estaVigente(?CarbonInterface $ahora = null): bool
    {
        $ahora ??= now();

        return $this->capturado_at->lessThanOrEqualTo($ahora)
            && $ahora->lessThan($this->vigenteHasta());
    }

    protected function casts(): array
    {
        return [
            'temperatura_inicio_c' => 'decimal:2',
            'temperatura_medio_c' => 'decimal:2',
            'temperatura_fondo_c' => 'decimal:2',
            'capturado_at' => 'immutable_datetime',
            'recibido_servidor_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
