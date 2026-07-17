<?php

use App\Http\Controllers\Api\AccesoOficinaController;
use App\Http\Controllers\Api\AccesoTabletController;
use App\Http\Controllers\Api\AdministracionAccesoController;
use App\Http\Controllers\Api\CamaraController;
use App\Http\Controllers\Api\CargaController;
use App\Http\Controllers\Api\CatalogoMaterialController;
use App\Http\Controllers\Api\CondicionSagController;
use App\Http\Controllers\Api\ConfiguracionCamaraController;
use App\Http\Controllers\Api\DespachoMaterialController;
use App\Http\Controllers\Api\MovimientoController;
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
    Route::middleware('can:consultar-despachos-materiales')->group(function () {
        Route::get('/materiales/catalogo', [CatalogoMaterialController::class, 'catalogo']);
        Route::get('/materiales/inventario', [DespachoMaterialController::class, 'inventario']);
        Route::get('/materiales/despachos', [DespachoMaterialController::class, 'index']);
        Route::get('/materiales/despachos/{despachoMaterial}', [DespachoMaterialController::class, 'show']);
    });
    Route::get('/materiales/kardex', [DespachoMaterialController::class, 'kardex'])
        ->middleware('can:consultar-kardex-materiales');
    Route::middleware('can:gestionar-despachos-materiales')->group(function () {
        Route::post('/materiales/despachos', [DespachoMaterialController::class, 'store']);
    });
    Route::post('/materiales/despachos/{despachoMaterial}/retirar', [DespachoMaterialController::class, 'retirar'])
        ->middleware('can:retirar-materiales');
    Route::post('/materiales/despachos/{despachoMaterial}/cancelar', [DespachoMaterialController::class, 'cancelar'])
        ->middleware('can:cancelar-despachos-materiales');

    Route::middleware('can:consultar-cargas-operacion')->group(function () {
        Route::get('/cargas/pendientes', [CargaController::class, 'pendientes']);
    });
    Route::get('/cargas/folios-disponibles', [CargaController::class, 'foliosDisponibles'])
        ->middleware('can:gestionar-cargas');
    Route::middleware('can:consultar-catalogo-cargas')->group(function () {
        Route::get('/cargas', [CargaController::class, 'index']);
        Route::get('/cargas/{carga}', [CargaController::class, 'show']);
    });
    Route::middleware('can:gestionar-cargas')->group(function () {
        Route::post('/cargas', [CargaController::class, 'store']);
        Route::put('/cargas/{carga}', [CargaController::class, 'update']);
        Route::post('/cargas/{carga}/folios', [CargaController::class, 'agregarFolios']);
        Route::delete('/cargas/{carga}/folios/{folio}', [CargaController::class, 'quitarFolio']);
        Route::post('/cargas/{carga}/publicar', [CargaController::class, 'publicar']);
        Route::post('/cargas/{carga}/cancelar', [CargaController::class, 'cancelar']);
    });

    Route::middleware('can:consultar-configuracion-camaras')->group(function () {
        Route::get('/configuracion/camaras', [ConfiguracionCamaraController::class, 'index']);
        Route::get('/configuracion/camaras/siguiente-codigo', [ConfiguracionCamaraController::class, 'siguienteCodigo']);
        Route::get('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'show']);
    });
    Route::post('/configuracion/camaras', [ConfiguracionCamaraController::class, 'store']);
    Route::put('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'update'])
        ->middleware('can:administrar-camaras');
    Route::delete('/configuracion/camaras/{camara}', [ConfiguracionCamaraController::class, 'destroy'])
        ->middleware('can:administrar-camaras');

    Route::middleware('can:administrar-accesos')->group(function () {
        Route::get('/administracion/accesos', [AdministracionAccesoController::class, 'index']);
        Route::post('/administracion/usuarios', [AdministracionAccesoController::class, 'crearUsuario']);
        Route::post('/administracion/dispositivos', [AdministracionAccesoController::class, 'crearDispositivo']);
    });
    Route::middleware('can:administrar-catalogos-materiales')->group(function () {
        Route::get('/administracion/materiales/items', [CatalogoMaterialController::class, 'items']);
        Route::post('/administracion/materiales/items', [CatalogoMaterialController::class, 'storeItem']);
        Route::put('/administracion/materiales/items/{itemMaterial}', [CatalogoMaterialController::class, 'updateItem']);
        Route::get('/administracion/materiales/destinos', [CatalogoMaterialController::class, 'destinos']);
        Route::post('/administracion/materiales/destinos', [CatalogoMaterialController::class, 'storeDestino']);
        Route::put('/administracion/materiales/destinos/{destinoMaterial}', [CatalogoMaterialController::class, 'updateDestino']);
    });
    Route::post('/camaras/{camara}/sesiones', [SesionEstibaController::class, 'store']);
    Route::post('/sesiones/{sesion}/cerrar', [SesionEstibaController::class, 'cerrar']);
    Route::post('/sesiones/{sesion}/cerrar-forzosamente', [SesionEstibaController::class, 'cerrarForzosamente']);

    Route::get('/movimientos/recientes', [MovimientoController::class, 'recientes']);
    Route::post('/movimientos/ubicar', [MovimientoController::class, 'ubicar']);
    Route::post('/movimientos/mover', [MovimientoController::class, 'mover']);

    Route::delete('/acceso-tablet', [AccesoTabletController::class, 'destroy'])
        ->middleware('auth:sanctum');
    Route::delete('/acceso-oficina', [AccesoOficinaController::class, 'destroy'])
        ->middleware('auth:sanctum');
});
