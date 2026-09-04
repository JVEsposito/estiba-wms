<?php

namespace App\Models;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoRetencionOperacional;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Models\Concerns\ImpideEliminacionFisica;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'folio_id',
    'bloqueo_folio_id',
    'estado',
    'motivo',
    'estado_operacional_anterior',
    'condicion_termica_anterior',
    'habilitacion_almacenamiento_anterior',
    'carga_id_original',
    'carga_folio_id_original',
    'retenido_por_user_id',
    'retenido_at',
    'liberado_por_user_id',
    'liberado_at',
    'contexto',
])]
class RetencionOperacionalFolio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected $table = 'retenciones_operacionales_folios';

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function cargaOriginal(): BelongsTo
    {
        return $this->belongsTo(Carga::class, 'carga_id_original');
    }

    public function asignacionCargaOriginal(): BelongsTo
    {
        return $this->belongsTo(CargaFolio::class, 'carga_folio_id_original');
    }

    public function retenidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retenido_por_user_id');
    }

    public function liberadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liberado_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoRetencionOperacional::class,
            'estado_operacional_anterior' => EstadoOperacionalFolio::class,
            'condicion_termica_anterior' => CondicionTermicaFolio::class,
            'habilitacion_almacenamiento_anterior' => HabilitacionAlmacenamientoFolio::class,
            'retenido_at' => 'datetime',
            'liberado_at' => 'datetime',
            'contexto' => 'array',
        ];
    }
}
