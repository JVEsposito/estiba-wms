<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cliente_id', 'ultimo_numero'])]
class CorrelativoEmbarqueCliente extends Model
{
    protected $table = 'correlativos_embarques_clientes';

    protected $primaryKey = 'cliente_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    protected function casts(): array
    {
        return ['ultimo_numero' => 'integer'];
    }
}
