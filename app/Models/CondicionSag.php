<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class CondicionSag extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'condiciones_sag';

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
