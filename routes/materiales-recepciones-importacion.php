<?php

use App\Http\Controllers\Api\ImportacionProductosRecepcionMaterialController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/api/materiales/recepciones/importaciones/previsualizar',
    ImportacionProductosRecepcionMaterialController::class,
)->middleware([
    'api',
    'auth:sanctum',
    'can:gestionar-recepciones-materiales',
]);
