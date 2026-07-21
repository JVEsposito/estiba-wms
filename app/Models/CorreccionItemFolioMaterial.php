<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operacion_id',
    'folio_id',
    'item_anterior_id',
    'item_nuevo_id',
    'cantidad',
    'motivo',
    'user_id',
    'ocurrido_at',
])]
class CorreccionItemFolioMaterial extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'correcciones_items_folios_materiales';

    public function folioMaterial(): BelongsTo
    {
        return $this->belongsTo(FolioMaterial::class, 'folio_id');
    }

    public function itemAnterior(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_anterior_id');
    }

    public function itemNuevo(): BelongsTo
    {
        return $this->belongsTo(ItemMaterial::class, 'item_nuevo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'ocurrido_at' => 'datetime',
        ];
    }
}
