<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id', 'payload_hash', 'recepcion_romana_id', 'validacion_mp_id', 'temporada_id',
    'numero_recepcion_snapshot', 'numero_guia_snapshot', 'cliente_nombre_snapshot',
    'categoria', 'tipo_envase', 'cantidad_afectada', 'descripcion',
    'registrado_por_user_id', 'dispositivo_id', 'registrado_at',
])]
class DefectoRecepcionMp extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'defectos_recepcion_mp';

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionRomana::class, 'recepcion_romana_id');
    }

    public function validacion(): BelongsTo
    {
        return $this->belongsTo(ValidacionMp::class, 'validacion_mp_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaDefectoRecepcionMp::class, 'defecto_recepcion_mp_id')
            ->orderBy('tipo')->orderBy('posicion');
    }

    protected function casts(): array
    {
        return ['registrado_at' => 'datetime'];
    }
}
