<?php

namespace App\Providers;

use App\Http\Controllers\Api\RecepcionMaterialController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RecepcionMaterialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/materiales/recepciones')
            ->group(function (): void {
                Route::middleware('can:consultar-recepciones-materiales')->group(function (): void {
                    Route::get('/catalogos', [RecepcionMaterialController::class, 'catalogos']);
                    Route::get('/folios-pendientes', [RecepcionMaterialController::class, 'foliosPendientes']);
                    Route::get('/', [RecepcionMaterialController::class, 'index']);
                    Route::get('/{recepcionMaterial}', [RecepcionMaterialController::class, 'show']);
                });

                Route::middleware('can:gestionar-recepciones-materiales')->group(function (): void {
                    Route::post('/', [RecepcionMaterialController::class, 'store']);
                    Route::post('/{recepcionMaterial}/confirmar', [RecepcionMaterialController::class, 'confirmar']);
                });

                Route::post('/{recepcionMaterial}/anular', [RecepcionMaterialController::class, 'anular'])
                    ->middleware('can:anular-recepciones-materiales');
            });
    }
}
