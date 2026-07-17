<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['folio_id', 'carga_folio_id'])]
class ReservaCargaFolio extends Model
{
    protected $table = 'reservas_carga_folio';

    protected $primaryKey = 'folio_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(CargaFolio::class, 'carga_folio_id');
    }
}
