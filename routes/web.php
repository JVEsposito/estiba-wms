<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/oficina/camaras', '/oficina/frigorifico/camaras');
Route::view('/oficina/frigorifico/camaras', 'office.cameras', [
    'navigationDomain' => 'frigorifico',
    'navigationOffice' => 'camaras',
    'cameraMode' => 'operacion',
]);
Route::view('/oficina/administracion/camaras', 'office.cameras', [
    'navigationDomain' => 'administracion',
    'navigationOffice' => 'configuracion-camaras',
    'cameraMode' => 'configuracion',
]);
Route::view('/oficina/cargas', 'office.loads');
Route::view('/oficina/accesos', 'office.accesses');
Route::view('/oficina/materiales', 'office.materials', ['navigationOffice' => 'recepcion']);
Route::view('/oficina/materiales/recepcion', 'office.materials', ['navigationOffice' => 'recepcion']);
Route::view('/oficina/materiales/transformacion', 'office.materials', ['navigationOffice' => 'transformacion']);
Route::redirect('/oficina/materiales/existencias', '/oficina/existencias');
Route::view('/oficina/validacion', 'office.validation');
Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');
Route::view('/oficina/prefrio', 'office.precooling');
Route::view('/oficina/gerencia', 'office.management');
Route::view('/oficina/existencias', 'office.inventory-exports');
Route::view('/oficina/romana', 'office.weighbridge');
Route::view('/oficina/envases/cuenta-corriente', 'office.container-accounts');
Route::view('/oficina/envases/despachos', 'office.container-dispatches');
Route::view('/oficina/materia-prima', 'office.raw-material');
Route::view('/oficina/materia-prima/lotes', 'office.raw-material');
Route::redirect('/oficina/materia-prima/romana', '/oficina/romana');
Route::redirect('/oficina/materia-prima/envases', '/oficina/envases/cuenta-corriente');
