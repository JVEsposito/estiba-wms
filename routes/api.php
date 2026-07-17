<?php

use App\Http\Controllers\Api\AccesoOficinaController;
use App\Http\Controllers\Api\AccesoTabletController;
use App\Http\Controllers\Api\AdministracionAccesoController;
use App\Http\Controllers\Api\AdministracionValidacionController;
use App\Http\Controllers\Api\AndenController;
use App\Http\Controllers\Api\CamaraController;
use App\Http\Controllers\Api\CargaController;
use App\Http\Controllers\Api\CatalogoMaterialController;
use App\Http\Controllers\Api\CatalogoValidacionController;
use App\Http\Controllers\Api\CondicionSagController;
use App\Http\Controllers\Api\ConfiguracionCamaraController;
use App\Http\Controllers\Api\DespachoFrigorificoController;
use App\Http\Controllers\Api\DespachoMaterialController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\SesionEstibaController;
use App\Http\Controllers\Api\ValidacionPalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/acceso-tablet', [AccesoTabletController::class, 'store'])->middleware('throttle:6,1');
Route::post('/acceso-oficina', [AccesoOficinaController::class, 'store'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/camaras', [CamaraController::class, 'index']);
    Route::get('/camaras/{camara}/plano', [CamaraController::class, 'plano']);
    Route::get('/condiciones-sag', [CondicionSagController::class, 'index']);

    Route::get('/validacion/catalogos', CatalogoValidacionController::class)
        ->middleware('can:validar-pallets');
    Route::post('/validacion/pallets', [ValidacionPalletController::class, 'store'])
        ->middleware('can:validar-pallets');
    Route::middleware('can:consultar-validaciones-pallet')->group(function () {
        Route::get('/validacion/pallets', [ValidacionPalletController::class, 'index']);
        Route::get('/validacion/pallets/{validacionPallet}', [ValidacionPalletController::class, 'show']);
    });
    Route::prefix('/administracion/validacion')
        ->middleware('can:administrar-catalogos-validacion')
        ->group(function () {
            Route::get('/', [AdministracionValidacionController::class, 'index']);
            Route::post('/temporadas', [AdministracionValidacionController::class, 'storeTemporada']);
            Route::put('/temporadas/{temporada}', [AdministracionValidacionController::class, 'updateTemporada']);
            Route::post('/temporadas/{temporada}/activar', [AdministracionValidacionController::class, 'activarTemporada']);
            Route::post('/articulos', [AdministracionValidacionController::class, 'storeArticulo']);
            Route::put('/articulos/{articuloValidacion}', [AdministracionValidacionController::class, 'updateArticulo']);
            Route::post('/origenes', [AdministracionValidacionController::class, 'storeOrigen']);
            Route::put('/origenes/{origenValidacion}', [AdministracionValidacionController::class, 'updateOrigen']);
            Route::post('/combinaciones', [AdministracionValidacionController::class, 'storeCombinacion']);
            Route::put('/combinaciones/{combinacionValidacion}', [AdministracionValidacionController::class, 'updateCombinacion']);
            Route::post('/importaciones/previsualizar', [AdministracionValidacionController::class, 'previsualizarImportacion']);
            Route::post('/importaciones/{importacionValidacion}/confirmar', [AdministracionValidacionController::class, 'confirmarImportacion']);
        });

    Route::middleware('can:consultar-despachos-materiales')->group(function () {
        Route::get('/materiales/catalogo', [CatalogoMaterialController::class, 'catalogo']);
        Route::get('/materiales/inventario', [DespachoMaterialController::class, 'inventario']);
        Route::get('/materiales/despachos', [DespachoMaterialController::class, 'index']);
        Route::get('/materiales/despachos/{despachoMaterial}', [DespachoMaterialController::class, 'show']);
    });
    Route::get('/materiales/kardex', [DespachoMaterialController::class, 'kardex'])
        ->middleware('can:consultar-kardex-materiales');
    Route::post('/materiales/despachos', [DespachoMaterialController::class, 'store'])
        ->middleware('can:gestionar-despachos-materiales');
    Route::post('/materiales/despachos/{despachoMaterial}/retirar', [DespachoMaterialController::class, 'retirar'])
        ->middleware('can:retirar-materiales');
    Route::post('/materiales/despachos/{despachoMaterial}/cancelar', [DespachoMaterialController::class, 'cancelar'])
        ->middleware('can:cancelar-despachos-materiales');

    Route::middleware('can:consultar-cargas-operacion')->group(function () {
        Route::get('/cargas/pendientes', [CargaController::class, 'pendientes']);
        Route::get('/cargas/{carga}/tareas', [DespachoFrigorificoController::class, 'tareas']);
        Route::get('/cargas/{carga}/plan-extraccion', [DespachoFrigorificoController::class, 'planExtraccion']);
        Route::get('/andenes', [AndenController::class, 'index']);
    });
    Route::get('/cargas/folios-disponibles', [CargaController::class, 'foliosDisponibles'])
        ->middleware('can:gestionar-cargas');
    Route::middleware('can:consultar-catalogo-cargas')->group(function () {
        Route::get('/cargas/incidencias', [DespachoFrigorificoController::class, 'incidencias']);
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
    Route::post('/cargas/asignaciones/{cargaFolio}/incidencias', [DespachoFrigorificoController::class, 'reportarIncidencia']);
    Route::post('/cargas/incidencias/{incidencia}/resolver', [DespachoFrigorificoController::class, 'resolverIncidencia']);
    Route::post('/cargas/asignaciones/{cargaFolio}/enviar-anden', [DespachoFrigorificoController::class, 'enviarAnden']);
    Route::post('/cargas/{carga}/cerrar-despacho', [DespachoFrigorificoController::class, 'cerrar']);

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
    Route::middleware('can:gestionar-andenes')->group(function () {
        Route::post('/administracion/andenes', [AndenController::class, 'store']);
        Route::put('/administracion/andenes/{anden}', [AndenController::class, 'update']);
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

    Route::delete('/acceso-tablet', [AccesoTabletController::class, 'destroy']);
    Route::delete('/acceso-oficina', [AccesoOficinaController::class, 'destroy']);
});
