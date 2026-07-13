<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['camara_id', 'sesion_estiba_id', 'adquirido_at'])]
class BloqueoCamara extends Model
{
    protected $table = 'bloqueos_camara';

    protected $primaryKey = 'camara_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function camara(): BelongsTo
    {
        return $this->belongsTo(Camara::class);
    }

    public function sesionEstiba(): BelongsTo
    {
        return $this->belongsTo(SesionEstiba::class);
    }

    protected function casts(): array
    {
        return ['adquirido_at' => 'datetime'];
    }
}
