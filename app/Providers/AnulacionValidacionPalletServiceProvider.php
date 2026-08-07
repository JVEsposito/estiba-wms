<?php

namespace App\Providers;

use App\Http\Controllers\Api\AnulacionValidacionPalletController;
use App\Models\CargaFolio;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\ProcesoPrefrioFolio;
use App\Models\Repaletizaje;
use App\Models\RepaletizajeDetalle;
use App\Models\ReservaCargaFolio;
use App\Models\UbicacionActual;
use App\Services\Validacion\ProteccionFolioAnulado;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AnulacionValidacionPalletServiceProvider extends ServiceProvider
{
    public function boot(ProteccionFolioAnulado $proteccion): void
    {
        Route::middleware('web')->group(function (): void {
            Route::view(
                '/oficina/validacion/anulaciones',
                'office.validation-annulments',
            )->name('office.validacion-anulaciones');
        });

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/validacion')
            ->group(function (): void {
                Route::get(
                    '/anulaciones',
                    [AnulacionValidacionPalletController::class, 'index'],
                )->middleware('can:consultar-validaciones-pallet');

                Route::post(
                    '/pallets/{validacionPallet}/anular',
                    [AnulacionValidacionPalletController::class, 'store'],
                )->middleware('can:rechazar-pallets');
            });

        Folio::updating(
            fn (Folio $folio) => $proteccion->asegurarMutable($folio),
        );
        UbicacionActual::saving(
            fn (UbicacionActual $registro) => $proteccion->asegurarOperableId($registro->folio_id),
        );
        Movimiento::saving(
            fn (Movimiento $registro) => $proteccion->asegurarOperableId($registro->folio_id),
        );
        CargaFolio::saving(
            fn (CargaFolio $registro) => $proteccion->asegurarOperableId($registro->folio_id),
        );
        ReservaCargaFolio::saving(
            fn (ReservaCargaFolio $registro) => $proteccion->asegurarOperableId($registro->folio_id),
        );
        ProcesoPrefrioFolio::saving(
            fn (ProcesoPrefrioFolio $registro) => $proteccion->asegurarOperableId($registro->folio_id),
        );
        RepaletizajeDetalle::saving(
            fn (RepaletizajeDetalle $registro) => $proteccion->asegurarOperableId($registro->folio_origen_id),
        );
        Repaletizaje::saving(function (Repaletizaje $registro) use ($proteccion): void {
            $proteccion->asegurarOperableId($registro->folio_resultante_id);
            $proteccion->asegurarOperableId($registro->folio_conservado_id);
        });
    }
}
