<?php

namespace App\Models;

use App\Enums\CondicionTermicaFolio;
use App\Enums\EstadoIntegracionFolio;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\FuenteHabilitacionAlmacenamiento;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\TipoBulto;
use App\Models\Concerns\ImpideEliminacionFisica;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'temporada_id',
    'numero_folio',
    'tipo_bulto',
    'condicion_sag_id',
    'estado_operacional',
    'condicion_termica',
    'habilitacion_almacenamiento',
    'fuente_habilitacion_almacenamiento',
    'habilitado_almacenamiento_at',
    'habilitado_almacenamiento_por_user_id',
    'retencion_termica_motivo',
    'fecha_ingreso',
    'activo',
    'variedad',
    'calibre',
    'marca',
    'exportadora',
    'origen_sistema',
    'identificador_externo',
    'estado_integracion',
    'sincronizado_at',
    'datos_externos',
])]
class Folio extends Model
{
    use HasUuids, ImpideEliminacionFisica;

    protected static function booted(): void
    {
        static::creating(function (Folio $folio): void {
            if ($folio->temporada_id !== null) {
                return;
            }

            $folio->temporada_id = Temporada::query()->where('activa', true)->value('id')
                ?? throw new DomainException(
                    'No existe una temporada global activa. Un administrador debe activarla desde Accesos.',
                );
        });
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function condicionSag(): BelongsTo
    {
        return $this->belongsTo(CondicionSag::class);
    }

    public function ubicacionActual(): HasOne
    {
        return $this->hasOne(UbicacionActual::class);
    }

    public function asignacionCargaActual(): HasOne
    {
        return $this->hasOne(CargaFolio::class)
            ->whereHas('reservaActiva');
    }

    public function asignacionesCarga(): HasMany
    {
        return $this->hasMany(CargaFolio::class);
    }

    public function reservaCargaActual(): HasOne
    {
        return $this->hasOne(ReservaCargaFolio::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function material(): HasOne
    {
        return $this->hasOne(FolioMaterial::class, 'folio_id');
    }

    public function procesosPrefrio(): HasMany
    {
        return $this->hasMany(ProcesoPrefrioFolio::class);
    }

    public function historialHabilitacionesAlmacenamiento(): HasMany
    {
        return $this->hasMany(RegistroHabilitacionAlmacenamiento::class);
    }

    public function habilitadoAlmacenamientoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'habilitado_almacenamiento_por_user_id');
    }

    protected function casts(): array
    {
        return [
            'tipo_bulto' => TipoBulto::class,
            'estado_operacional' => EstadoOperacionalFolio::class,
            'condicion_termica' => CondicionTermicaFolio::class,
            'habilitacion_almacenamiento' => HabilitacionAlmacenamientoFolio::class,
            'fuente_habilitacion_almacenamiento' => FuenteHabilitacionAlmacenamiento::class,
            'habilitado_almacenamiento_at' => 'datetime',
            'fecha_ingreso' => 'datetime',
            'activo' => 'boolean',
            'sincronizado_at' => 'datetime',
            'datos_externos' => 'array',
            'estado_integracion' => EstadoIntegracionFolio::class,
        ];
    }
}
