<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paquete de archivo (respaldo restaurable) de una temporada cerrada. */
#[Fillable([
    'temporada_id',
    'estado',
    'disco',
    'ruta',
    'tamano_bytes',
    'sha256',
    'manifiesto',
    'mensaje_error',
    'solicitado_por_user_id',
    'iniciado_at',
    'generado_at',
    'verificado_at',
])]
class ArchivoTemporada extends Model
{
    use HasUuids;

    protected $table = 'archivos_temporada';

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'manifiesto' => 'array',
            'tamano_bytes' => 'integer',
            'iniciado_at' => 'datetime',
            'generado_at' => 'datetime',
            'verificado_at' => 'datetime',
        ];
    }
}
