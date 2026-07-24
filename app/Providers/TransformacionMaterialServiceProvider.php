<?php

namespace App\Providers;

use App\Http\Controllers\Api\TransformacionMaterialController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TransformacionMaterialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/materiales/transformaciones')
            ->group(function (): void {
                Route::middleware('can:consultar-transformaciones-materiales')->group(function (): void {
                    Route::get('/recetas', [TransformacionMaterialController::class, 'recetas']);
                    Route::get('/ordenes', [TransformacionMaterialController::class, 'ordenes']);
                    Route::get('/ordenes/{ordenTransformacionMaterial}', [TransformacionMaterialController::class, 'mostrarOrden']);
                });

                Route::middleware('can:administrar-recetas-materiales')->group(function (): void {
                    Route::post('/recetas', [TransformacionMaterialController::class, 'guardarReceta']);
                    Route::post('/recetas/{recetaMaterial}/versiones', [TransformacionMaterialController::class, 'guardarVersionReceta']);
                });

                Route::middleware('can:gestionar-transformaciones-materiales')->group(function (): void {
                    Route::post('/ordenes', [TransformacionMaterialController::class, 'guardarOrden']);
                    Route::post('/ordenes/{ordenTransformacionMaterial}/planificar', [TransformacionMaterialController::class, 'planificar']);
                    Route::post('/ordenes/{ordenTransformacionMaterial}/cancelar', [TransformacionMaterialController::class, 'cancelar']);
                });
            });
    }
}
