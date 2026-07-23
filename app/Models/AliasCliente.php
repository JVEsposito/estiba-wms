<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cliente_id', 'origen', 'codigo', 'nombre'])]
class AliasCliente extends Model
{
    use HasUuids;

    protected $table = 'aliases_clientes';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
