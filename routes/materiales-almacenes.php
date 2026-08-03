<?php

use App\Http\Controllers\Api\AlmacenMaterialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/materiales/almacenes')
    ->group(function (): void {
        Route::get('/', [AlmacenMaterialController::class, 'index']);
        Route::get('/movimientos', [AlmacenMaterialController::class, 'movimientos']);
        Route::post('/movimientos', [AlmacenMaterialController::class, 'store']);
    });

Route::middleware('web')
    ->get(
        '/oficina/materiales/almacenes',
        fn () => view('office.material-warehouses'),
    )
    ->name('office.materials.warehouses');
