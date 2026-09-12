<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'item_duplicado_id',
    'item_canonico_id',
    'snapshot',
    'motivo',
    'user_id',
    'ocurrido_at',
])]
class RegularizacionItemMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'regularizaciones_items_materiales';

    public function itemDuplicado(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_duplicado_id');
    }

    public function itemCanonico(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_canonico_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'ocurrido_at' => 'datetime',
        ];
    }
}
