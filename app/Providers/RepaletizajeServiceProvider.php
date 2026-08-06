<?php

namespace App\Providers;

use App\Http\Controllers\Api\RepaletizajeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RepaletizajeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::view(
                '/oficina/validacion/repaletizajes',
                'office.repalletizing',
            )->name('office.repaletizajes');
        });

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/validacion/repaletizajes')
            ->group(function (): void {
                Route::middleware('can:consultar-validaciones-pallet')->group(function (): void {
                    Route::get('/', [RepaletizajeController::class, 'index']);
                    Route::get(
                        '/folios/{numeroFolio}',
                        [RepaletizajeController::class, 'buscarFolio'],
                    );
                    Route::get(
                        '/{repaletizaje}',
                        [RepaletizajeController::class, 'show'],
                    );
                });

                Route::post('/', [RepaletizajeController::class, 'store'])
                    ->middleware('can:validar-pallets');
                Route::post(
                    '/{repaletizaje}/anular',
                    [RepaletizajeController::class, 'anular'],
                )->middleware('can:rechazar-pallets');
            });
    }
}
