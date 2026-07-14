<?php

namespace App\Models;

use App\Enums\TipoEventoCarga;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carga_id',
    'folio_id',
    'user_id',
    'tipo',
    'datos',
])]
class EventoCarga extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'eventos_carga';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoCarga::class,
            'datos' => 'array',
        ];
    }
}
