<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'tipo',
    'token_hash',
    'expira_at',
    'ultimo_uso_at',
    'revocado_at',
])]
class ConexionExistencia extends Model
{
    use HasUuids;

    protected $table = 'conexiones_existencias';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaVigente(): bool
    {
        return $this->revocado_at === null
            && ($this->expira_at === null || $this->expira_at->isFuture())
            && $this->usuario?->activo === true;
    }

    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
            'ultimo_uso_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }
}
