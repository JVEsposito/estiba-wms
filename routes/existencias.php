<?php

use App\Http\Controllers\Api\ExistenciaController;
use Illuminate\Support\Facades\Route;

Route::get('/existencias/{tipo}/consulta', [ExistenciaController::class, 'consulta']);

Route::middleware('auth:sanctum')->prefix('existencias')->group(function (): void {
    Route::get('/', [ExistenciaController::class, 'index']);
    Route::get('/{tipo}/corte', [ExistenciaController::class, 'corte']);
    Route::post('/{tipo}/conexion-excel', [ExistenciaController::class, 'crearConexion']);
    Route::post('/conexiones/{conexionExistencia}/revocar', [ExistenciaController::class, 'revocar']);
});
