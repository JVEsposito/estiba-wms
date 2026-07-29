<?php

namespace App\Observers;

use App\Models\Camara;
use App\Models\CargaFolio;
use App\Models\ClienteMaterial;
use App\Models\Folio;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\Posicion;
use App\Models\PosicionTunelPrefrio;
use App\Models\ProcesoPrefrio;
use App\Models\ProcesoPrefrioFolio;
use App\Models\RecepcionRomana;
use App\Models\ReservaCargaFolio;
use App\Models\TemporadaMaterial;
use App\Models\TunelPrefrio;
use App\Models\UbicacionActual;
use App\Services\Gerencia\ServicioPanelGerencial;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class InvalidarPanelGerencialObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ServicioPanelGerencial $panel,
    ) {}

    /**
     * @return array<int, class-string<Model>>
     */
    public static function modelosObservados(): array
    {
        return [
            Camara::class,
            Posicion::class,
            UbicacionActual::class,
            Folio::class,
            CargaFolio::class,
            ReservaCargaFolio::class,
            FolioMaterial::class,
            ItemMaterial::class,
            ClienteMaterial::class,
            TemporadaMaterial::class,
            TunelPrefrio::class,
            PosicionTunelPrefrio::class,
            ProcesoPrefrio::class,
            ProcesoPrefrioFolio::class,
            RecepcionRomana::class,
        ];
    }

    public function saved(Model $modelo): void
    {
        $this->panel->invalidar();
    }

    public function deleted(Model $modelo): void
    {
        $this->panel->invalidar();
    }

    public function restored(Model $modelo): void
    {
        $this->panel->invalidar();
    }
}
