<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carga_id',
    'folio_id',
    'asignado_por_user_id',
    'asignado_at',
])]
class CargaFolio extends Model
{
    use HasUuids;

    protected $table = 'carga_folios';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'asignado_at' => 'datetime',
        ];
    }
}
