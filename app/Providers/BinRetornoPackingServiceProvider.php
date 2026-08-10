<?php

namespace App\Providers;

use App\Http\Controllers\Api\BinRetornoPackingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BinRetornoPackingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::view(
                '/oficina/materia-prima/retornos-packing',
                'office.raw-material-returns',
            )->name('office.materia-prima.retornos-packing');
        });

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/materia-prima/fruta-proceso/retornos-bin')
            ->group(function (): void {
                Route::middleware('can:consultar-fruta-proceso')->group(function (): void {
                    Route::get('/resumen', [BinRetornoPackingController::class, 'resumen']);
                    Route::get('/procesos', [BinRetornoPackingController::class, 'procesos']);
                    Route::get('/catalogos', [BinRetornoPackingController::class, 'catalogos']);
                    Route::get('/bins', [BinRetornoPackingController::class, 'index']);
                    Route::get('/legacy', [BinRetornoPackingController::class, 'legacy']);
                });

                Route::post('/bins', [BinRetornoPackingController::class, 'store'])
                    ->middleware('can:entregar-fruta-proceso');
                Route::post(
                    '/bins/{binRetornoPacking}/regularizar',
                    [BinRetornoPackingController::class, 'regularizar'],
                )->middleware('can:entregar-fruta-proceso');

                Route::post(
                    '/legacy/{retornoPacking}/migrar',
                    [BinRetornoPackingController::class, 'migrarLegacy'],
                )->middleware('can:anular-entregas-fruta-proceso');
                Route::post(
                    '/legacy/{retornoPacking}/descartar',
                    [BinRetornoPackingController::class, 'descartarLegacy'],
                )->middleware('can:anular-entregas-fruta-proceso');
            });
    }
}
