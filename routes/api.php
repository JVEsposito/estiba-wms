<?php

use App\Http\Controllers\Api\CamaraController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\SesionEstibaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/camaras', [CamaraController::class, 'index']);
    Route::get('/camaras/{camara}/plano', [CamaraController::class, 'plano']);
    Route::post('/camaras/{camara}/sesiones', [SesionEstibaController::class, 'store']);
    Route::post('/sesiones/{sesion}/cerrar', [SesionEstibaController::class, 'cerrar']);

    Route::get('/movimientos/recientes', [MovimientoController::class, 'recientes']);
    Route::post('/movimientos/ubicar', [MovimientoController::class, 'ubicar']);
    Route::post('/movimientos/mover', [MovimientoController::class, 'mover']);
});
