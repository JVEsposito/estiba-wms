<?php

namespace App\Models;

use App\Enums\EstadoRecepcionRomana;
use App\Enums\TipoEnvaseRomana;
use App\Enums\TipoServicioRomana;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'operacion_id',
    'payload_hash',
    'numero_recepcion',
    'cliente_id',
    'cliente_codigo_snapshot',
    'cliente_nombre_snapshot',
    'tipo_servicio',
    'cantidad_envases_declarados',
    'tipo_envase_declarado',
    'numero_guia_despacho',
    'patente_camion',
    'patente_carro',
    'rut_conductor',
    'nombre_conductor',
    'peso_bruto',
    'peso_tara',
    'peso_neto',
    'estado',
    'ingreso_at',
    'ingreso_confirmado_at',
    'salida_at',
    'version',
    'creado_por_user_id',
    'ingreso_confirmado_por_user_id',
    'cerrado_por_user_id',
    'observacion',
    'observacion_cierre',
])]
class RecepcionRomana extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'recepciones_romana';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoRecepcionRomana::class, 'recepcion_romana_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function ingresoConfirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingreso_confirmado_por_user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_servicio' => TipoServicioRomana::class,
            'tipo_envase_declarado' => TipoEnvaseRomana::class,
            'estado' => EstadoRecepcionRomana::class,
            'cantidad_envases_declarados' => 'integer',
            'peso_bruto' => 'decimal:2',
            'peso_tara' => 'decimal:2',
            'peso_neto' => 'decimal:2',
            'ingreso_at' => 'datetime',
            'ingreso_confirmado_at' => 'datetime',
            'salida_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
