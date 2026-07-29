<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'numero_folio',
    'numero_correlativo',
    'recepcion_material_id_original',
    'motivo',
    'liberado_por_user_id',
])]
class FolioMaterialLiberado extends Model
{
    use HasUuids;

    protected $table = 'folios_materiales_liberados';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'numero_correlativo' => 'integer',
        ];
    }
}
