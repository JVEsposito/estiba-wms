<?php

use App\Http\Controllers\Api\AccesoOficinaController;
use App\Http\Controllers\Api\AccesoTabletController;
use App\Http\Controllers\Api\AdministracionAccesoController;
use App\Http\Controllers\Api\CamaraController;
use App\Http\Controllers\Api\CatalogoMaterialController;
use App\Http\Controllers\Api\CargaController;
use App\Http\Controllers\Api\CondicionSagController;
use App\Http\Controllers\Api\ConfiguracionCamaraController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\DespachoMaterialController;
use App\Http\Controllers\Api\SesionEstibaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/acceso-tablet', [AccesoTabletController::class, 'store'])
    ->middleware('throttle:6,1');
Route::post('/acceso-oficina', [AccesoOficinaController::class, 'store'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/camaras', [CamaraController::class, 'index']);
    Route::get('/camaras/{camara}/plano', [CamaraController::class, 'plano']);
    Route::get('/condiciones-sag', [CondicionSagController::class, 'index']);
    Route::get('/materiales/catalogo', [CatalogoMaterialController::class, 'catalogo']);
    Route::get('/materiales/inventario', [DespachoMaterialController::class, 'inventario']);
    Route::get('/materiales/kardex', [DespachoMaterialController::class, 'kardex']);
    Route::get('/materiales/despachos', [DespachoMaterialController::class, 'index']);
    Route::post('/materiales/despachos', [DespachoMaterialController::class, 'store']);
    Route::get('/materiales/despachos/{despachoMaterial}', [DespachoMaterialController::class, 'show']);
    Route::post('/materiales/despachos/{despachoMaterial}/retirar', [DespachoMaterialController::class, 'retirar']);
    Route::post('/materiales/despachos/{despachoMaterial}/cancelar', [DespachoMaterialController::class, 'cancelar']);

    Route::get('/cargas/pendientes', [CargaController::class, 'pendientes']);
    Route::get('/cargas', [CargaController::class, 'index']);
    Route::post('/cargas', [CargaController::class, 'store']);
    Route::get('/cargas/folios-disponibles', [CargaController::class, 'foliosDisponibles']);
    Route::get('/cargas/{carga}', [CargaController::class, 'show']);
    Route::put('/cargas/{carga}', [CargaController::class, 'update']);
    Route::post('/cargas/{carga}/folios', [CargaController::class, 'agregarFolios']);
    Route::delete('/cargas/{carga}/folios/{folio}', [CargaController::class, 'quitarFolio']);
    Route::post('/cargas/{carga}/publicar', [CargaController::class, 'publicar']);
    Route::post('/cargas/{carga}/cancelar', [CargaController::class, 'cancelar']);

    Route::get('/configuracion/camaras', [ConfiguracionCamaraController::class, 'index']);
    Route::get('/configuracion/camaras/siguiente-codigo', [ConfiguracionCamaraController::class, 'siguienteCodigo']);
    Route::post('/configuracion/camaras', [ConfiguracionCamaraController::class, 'store']);
    Route::get('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'show']);
    Route::put('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'update']);
    Route::delete('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'destroy']);

    Route::get('/administracion/accesos', [AdministracionAccesoController::class, 'index']);
    Route::post('/administracion/usuarios', [AdministracionAccesoController::class, 'crearUsuario']);
    Route::post('/administracion/dispositivos', [AdministracionAccesoController::class, 'crearDispositivo']);
    Route::get('/administracion/materiales/items', [CatalogoMaterialController::class, 'items']);
    Route::post('/administracion/materiales/items', [CatalogoMaterialController::class, 'storeItem']);
    Route::put('/administracion/materiales/items/{itemMaterial}', [CatalogoMaterialController::class, 'updateItem']);
    Route::get('/administracion/materiales/destinos', [CatalogoMaterialController::class, 'destinos']);
    Route::post('/administracion/materiales/destinos', [CatalogoMaterialController::class, 'storeDestino']);
    Route::put('/administracion/materiales/destinos/{destinoMaterial}', [CatalogoMaterialController::class, 'updateDestino']);
    Route::post('/camaras/{camara}/sesiones', [SesionEstibaController::class, 'store']);
    Route::post('/sesiones/{sesion}/cerrar', [SesionEstibaController::class, 'cerrar']);

    Route::get('/movimientos/recientes', [MovimientoController::class, 'recientes']);
    Route::post('/movimientos/ubicar', [MovimientoController::class, 'ubicar']);
    Route::post('/movimientos/mover', [MovimientoController::class, 'mover']);

    Route::delete('/acceso-tablet', [AccesoTabletController::class, 'destroy'])
        ->middleware('auth:sanctum');
    Route::delete('/acceso-oficina', [AccesoOficinaController::class, 'destroy'])
        ->middleware('auth:sanctum');
});
