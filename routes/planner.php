<?php

use App\Http\Controllers\Api\DespachoDirectoPlanificadorController;
use App\Http\Controllers\Api\PlanOperacionalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:operar-camaras-productos'])->group(function () {
    Route::get(
        '/planes-operacionales/{planOperacional}/snapshot',
        [PlanOperacionalController::class, 'snapshot'],
    );
    Route::post(
        '/planes-operacionales/{planOperacional}/frontera',
        [PlanOperacionalController::class, 'materializarFrontera'],
    );
    Route::post(
        '/tareas-movimiento/{tareaMovimiento}/iniciar',
        [PlanOperacionalController::class, 'iniciar'],
    );
    Route::post(
        '/tareas-movimiento/{tareaMovimiento}/completar-prefrio-directo',
        [DespachoDirectoPlanificadorController::class, 'completarPrefrio'],
    );
});
