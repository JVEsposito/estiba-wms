<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'migracion_temporada_id',
    'folio_id',
    'item_material_origen_id',
    'item_material_destino_id',
    'cantidad',
])]
class MigracionTemporadaFolio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'migraciones_temporadas_folios';

    public function migracion(): BelongsTo
    {
        return $this->belongsTo(MigracionTemporada::class, 'migracion_temporada_id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3'];
    }
}
