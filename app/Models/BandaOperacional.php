<?php

namespace App\Models;

use App\Enums\EstadoBandaOperacional;
use App\Enums\ModoBandaOperacional;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'camara_id',
    'numero',
    'usos_permitidos',
    'modo',
    'motivo_estado',
    'actualizado_por_user_id',
    'version',
])]
class BandaOperacional extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'bandas_operacionales';

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_user_id');
    }

    public function estadoCalculado(int $capacidadEfectiva, int $ocupadas): EstadoBandaOperacional
    {
        if ($this->modo === ModoBandaOperacional::Bloqueada) {
            return EstadoBandaOperacional::Bloqueada;
        }

        if ($this->modo === ModoBandaOperacional::EnVaciado) {
            return EstadoBandaOperacional::EnVaciado;
        }

        if ($capacidadEfectiva === 0) {
            return EstadoBandaOperacional::Bloqueada;
        }

        if ($ocupadas === 0) {
            return EstadoBandaOperacional::Libre;
        }

        if ($ocupadas >= $capacidadEfectiva) {
            return EstadoBandaOperacional::Completa;
        }

        return EstadoBandaOperacional::Parcial;
    }

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'usos_permitidos' => 'array',
            'modo' => ModoBandaOperacional::class,
            'version' => 'integer',
        ];
    }
}
