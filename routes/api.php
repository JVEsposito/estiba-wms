<?php

use App\Http\Controllers\Api\AccesoOficinaController;
use App\Http\Controllers\Api\AccesoTabletController;
use App\Http\Controllers\Api\AdministracionAccesoController;
use App\Http\Controllers\Api\AdministracionValidacionController;
use App\Http\Controllers\Api\AndenController;
use App\Http\Controllers\Api\CamaraController;
use App\Http\Controllers\Api\CargaController;
use App\Http\Controllers\Api\CatalogoJerarquicoValidacionController;
use App\Http\Controllers\Api\CatalogoMaterialController;
use App\Http\Controllers\Api\CatalogoValidacionController;
use App\Http\Controllers\Api\CondicionSagController;
use App\Http\Controllers\Api\ConfiguracionCamaraController;
use App\Http\Controllers\Api\DespachoFrigorificoController;
use App\Http\Controllers\Api\DespachoMaterialController;
use App\Http\Controllers\Api\FolioPrefrioController;
use App\Http\Controllers\Api\ImportacionCatalogoMaterialController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\NotificacionOperacionalController;
use App\Http\Controllers\Api\ProcesoPrefrioController;
use App\Http\Controllers\Api\SesionEstibaController;
use App\Http\Controllers\Api\TunelPrefrioController;
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

    Route::middleware('can:consultar-prefrio')->group(function () {
        Route::get('/prefrio/tuneles', [TunelPrefrioController::class, 'index']);
        Route::get('/prefrio/tuneles/{tunelPrefrio}', [TunelPrefrioController::class, 'show']);
        Route::get('/prefrio/folios-disponibles', [FolioPrefrioController::class, 'index']);
        Route::get('/prefrio/procesos', [ProcesoPrefrioController::class, 'index']);
        Route::get('/prefrio/resumen', [ProcesoPrefrioController::class, 'resumen']);
        Route::get('/prefrio/procesos/{procesoPrefrio}', [ProcesoPrefrioController::class, 'show']);
    });
    Route::middleware('can:operar-prefrio')->group(function () {
        Route::post('/prefrio/procesos', [ProcesoPrefrioController::class, 'store']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/folios', [ProcesoPrefrioController::class, 'agregarFolio']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/folios/{asignacionPrefrio}/retirar', [ProcesoPrefrioController::class, 'retirarFolio']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/confirmar-armado', [ProcesoPrefrioController::class, 'confirmarArmado']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/iniciar', [ProcesoPrefrioController::class, 'iniciar']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/eventos/{tipo}', [ProcesoPrefrioController::class, 'registrarEvento']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/verificar', [ProcesoPrefrioController::class, 'enviarAVerificacion']);
    });
    Route::middleware('can:supervisar-prefrio')->group(function () {
        Route::post('/prefrio/procesos/{procesoPrefrio}/aprobar', [ProcesoPrefrioController::class, 'aprobar']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/reprocesar', [ProcesoPrefrioController::class, 'reprocesar']);
        Route::post('/prefrio/procesos/{procesoPrefrio}/cancelar', [ProcesoPrefrioController::class, 'cancelar']);
    });
    Route::middleware('can:administrar-tuneles-prefrio')->group(function () {
        Route::get('/administracion/prefrio/tuneles/siguiente-codigo', [TunelPrefrioController::class, 'siguienteCodigo']);
        Route::post('/administracion/prefrio/tuneles', [TunelPrefrioController::class, 'store']);
        Route::put('/administracion/prefrio/tuneles/{tunelPrefrio}', [TunelPrefrioController::class, 'update']);
    });

    Route::get('/validacion/catalogos', CatalogoValidacionController::class)
        ->middleware('can:validar-pallets');
    Route::post('/validacion/pallets', [ValidacionPalletController::class, 'store'])
        ->middleware('can:validar-pallets');
    Route::middleware('can:consultar-validaciones-pallet')->group(function () {
        Route::get('/validacion/pallets', [ValidacionPalletController::class, 'index']);
        Route::get('/validacion/pallets/{validacionPallet}', [ValidacionPalletController::class, 'show']);
    });
    Route::prefix('administracion/validacion')
        ->middleware('can:administrar-catalogos-validacion')
        ->group(function () {
            Route::get('/', [AdministracionValidacionController::class, 'index']);
            Route::get('/temporadas/{temporada}/catalogo', [CatalogoJerarquicoValidacionController::class, 'index']);
            Route::post('/clientes', [CatalogoJerarquicoValidacionController::class, 'storeCliente']);
            Route::put('/clientes/{clienteValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateCliente']);
            Route::post('/marcas', [CatalogoJerarquicoValidacionController::class, 'storeMarca']);
            Route::put('/marcas/{marcaValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateMarca']);
            Route::post('/especies', [CatalogoJerarquicoValidacionController::class, 'storeEspecie']);
            Route::put('/especies/{especieValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateEspecie']);
            Route::post('/categorias', [CatalogoJerarquicoValidacionController::class, 'storeCategoria']);
            Route::put('/categorias/{categoriaValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateCategoria']);
            Route::post('/variedades', [CatalogoJerarquicoValidacionController::class, 'storeVariedad']);
            Route::put('/variedades/{variedadValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateVariedad']);
            Route::post('/calibres', [CatalogoJerarquicoValidacionController::class, 'storeCalibre']);
            Route::put('/calibres/{calibreValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateCalibre']);
            Route::post('/envases', [CatalogoJerarquicoValidacionController::class, 'storeEnvase']);
            Route::put('/envases/{envaseValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateEnvase']);
            Route::post('/csg', [CatalogoJerarquicoValidacionController::class, 'storeCsg']);
            Route::put('/csg/{csgValidacion}', [CatalogoJerarquicoValidacionController::class, 'updateCsg']);
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

    Route::get('/notificaciones-operacionales', [NotificacionOperacionalController::class, 'index']);
    Route::post('/notificaciones-operacionales/{notificacionOperacional}/leer', [NotificacionOperacionalController::class, 'marcarLeida']);
    Route::post('/notificaciones-operacionales/{notificacionOperacional}/confirmar', [NotificacionOperacionalController::class, 'confirmar']);

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
        Route::get('/administracion/materiales/temporadas', [CatalogoMaterialController::class, 'temporadas']);
        Route::post('/administracion/materiales/temporadas', [CatalogoMaterialController::class, 'storeTemporada']);
        Route::put('/administracion/materiales/temporadas/{temporadaMaterial}', [CatalogoMaterialController::class, 'updateTemporada']);
        Route::post('/administracion/materiales/temporadas/{temporadaMaterial}/activar', [CatalogoMaterialController::class, 'activarTemporada']);
        Route::get('/administracion/materiales/clientes', [CatalogoMaterialController::class, 'clientes']);
        Route::post('/administracion/materiales/clientes', [CatalogoMaterialController::class, 'storeCliente']);
        Route::put('/administracion/materiales/clientes/{clienteMaterial}', [CatalogoMaterialController::class, 'updateCliente']);
        Route::get('/administracion/materiales/importaciones', [ImportacionCatalogoMaterialController::class, 'index']);
        Route::post('/administracion/materiales/importaciones/previsualizar', [ImportacionCatalogoMaterialController::class, 'previsualizar']);
        Route::post('/administracion/materiales/importaciones/{importacionCatalogoMaterial}/confirmar', [ImportacionCatalogoMaterialController::class, 'confirmar']);
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
