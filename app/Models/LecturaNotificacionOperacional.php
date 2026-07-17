<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'notificacion_operacional_id',
    'user_id',
    'leida_at',
    'confirmada_at',
])]
class LecturaNotificacionOperacional extends Model
{
    use HasUuids;

    protected $table = 'lecturas_notificaciones_operacionales';

    public function notificacion(): BelongsTo
    {
        return $this->belongsTo(NotificacionOperacional::class, 'notificacion_operacional_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'leida_at' => 'datetime',
            'confirmada_at' => 'datetime',
        ];
    }
}
