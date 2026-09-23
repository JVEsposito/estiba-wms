<?php

namespace App\Models;

use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['defecto_recepcion_mp_id', 'tipo', 'posicion', 'ruta', 'mime', 'tamano_bytes', 'sha256'])]
class EvidenciaDefectoRecepcionMp extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'evidencias_defecto_recepcion_mp';

    public function defecto(): BelongsTo
    {
        return $this->belongsTo(DefectoRecepcionMp::class, 'defecto_recepcion_mp_id');
    }
}
