<?php

namespace App\Models;

use App\Enums\EstadoGuiaDespachoEnvase;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id', 'numero', 'temporada_id', 'cliente_id', 'estado', 'salida_at',
    'patente_camion', 'rut_conductor', 'nombre_conductor', 'observacion', 'version',
    'creado_por_user_id', 'confirmado_por_user_id', 'anulado_por_user_id',
    'confirmado_at', 'anulado_at', 'motivo_anulacion',
])]
class GuiaDespachoEnvase extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'guias_despacho_envases';

    public function temporada(): BelongsTo { return $this->belongsTo(Temporada::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function detalles(): HasMany { return $this->hasMany(DetalleGuiaDespachoEnvase::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por_user_id'); }
    public function confirmadoPor(): BelongsTo { return $this->belongsTo(User::class, 'confirmado_por_user_id'); }
    public function anuladoPor(): BelongsTo { return $this->belongsTo(User::class, 'anulado_por_user_id'); }

    protected function casts(): array
    {
        return [
            'estado' => EstadoGuiaDespachoEnvase::class,
            'salida_at' => 'datetime',
            'confirmado_at' => 'datetime',
            'anulado_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
