<?php

namespace App\Providers;

use App\Models\FolioMaterial;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\UbicacionActual;
use App\Observers\FolioMaterialAlmacenObserver;
use App\Observers\MovimientoAlmacenMaterialObserver;
use App\Observers\UbicacionActualAlmacenObserver;
use App\Services\Materiales\ServicioConsultaInventarioMaterial;
use App\Services\Materiales\ServicioConsultaInventarioMaterialDistribuido;
use App\Services\Materiales\ServicioDespachoMaterial;
use App\Services\Materiales\ServicioDespachoMaterialDistribuido;
use App\Services\Materiales\ServicioReservaFifoMaterial;
use App\Services\Materiales\ServicioReservaFifoMaterialDistribuido;
use Illuminate\Support\ServiceProvider;

class CustodiaDistribuidaMaterialesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ServicioReservaFifoMaterial::class,
            ServicioReservaFifoMaterialDistribuido::class,
        );
        $this->app->bind(
            ServicioDespachoMaterial::class,
            ServicioDespachoMaterialDistribuido::class,
        );
        $this->app->bind(
            ServicioConsultaInventarioMaterial::class,
            ServicioConsultaInventarioMaterialDistribuido::class,
        );
    }

    public function boot(): void
    {
        FolioMaterial::observe(FolioMaterialAlmacenObserver::class);
        MovimientoAlmacenMaterial::observe(MovimientoAlmacenMaterialObserver::class);
        UbicacionActual::observe(UbicacionActualAlmacenObserver::class);

        $this->loadRoutesFrom(base_path('routes/materiales-almacenes.php'));
    }
}
