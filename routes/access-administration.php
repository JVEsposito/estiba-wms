<?php

use App\Http\Controllers\Api\AdministracionAccesoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:administrar-accesos'])->group(function (): void {
    Route::put(
        '/administracion/usuarios/{usuario}',
        [AdministracionAccesoController::class, 'actualizarUsuario'],
    );
});
