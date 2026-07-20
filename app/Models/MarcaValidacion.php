<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cliente_validacion_id', 'nombre', 'codigo_externo', 'activo'])]
class MarcaValidacion extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'marcas_validacion';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(ClienteValidacion::class, 'cliente_validacion_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
