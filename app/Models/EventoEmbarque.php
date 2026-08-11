<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['embarque_id', 'user_id', 'tipo', 'datos'])]
class EventoEmbarque extends Model
{
    use HasUuids;

    protected $table = 'eventos_embarque';

    public function embarque(): BelongsTo
    {
        return $this->belongsTo(Embarque::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }
}
