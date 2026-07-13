<?php

namespace App\Models;

use App\Enums\EstadoCamara;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['codigo', 'nombre', 'tipo', 'estado'])]
class Camara extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    public function posiciones(): HasMany
    {
        return $this->hasMany(Posicion::class);
    }

    public function sesionesEstiba(): HasMany
    {
        return $this->hasMany(SesionEstiba::class);
    }

    public function bloqueo(): HasOne
    {
        return $this->hasOne(BloqueoCamara::class);
    }

    public function movimientosOrigen(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'camara_origen_id');
    }

    public function movimientosDestino(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'camara_destino_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoCamara::class,
            'version_plano' => 'integer',
        ];
    }
}
