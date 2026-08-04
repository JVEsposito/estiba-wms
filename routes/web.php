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
Route::view('/oficina/materiales', 'office.materials', [
    'navigationOffice' => 'resumen',
    'materialsSection' => 'resumen',
]);
Route::view('/oficina/materiales/catalogos', 'office.materials', [
    'navigationOffice' => 'catalogos',
    'materialsSection' => 'catalogos',
]);
Route::view('/oficina/materiales/recepcion', 'office.materials', [
    'navigationOffice' => 'recepcion',
    'materialsSection' => 'recepcion',
]);
Route::view('/oficina/materiales/recepciones', 'office.materials', [
    'navigationOffice' => 'recepciones',
    'materialsSection' => 'recepciones',
]);
Route::view('/oficina/materiales/inventario', 'office.materials', [
    'navigationOffice' => 'inventario',
    'materialsSection' => 'inventario',
]);
Route::view('/oficina/materiales/despachos', 'office.materials', [
    'navigationOffice' => 'despachos',
    'materialsSection' => 'despachos',
]);
Route::view('/oficina/materiales/recetas', 'office.materials', [
    'navigationOffice' => 'recetas',
    'materialsSection' => 'recetas',
]);
Route::view('/oficina/materiales/ordenes', 'office.materials', [
    'navigationOffice' => 'ordenes',
    'materialsSection' => 'ordenes',
]);
Route::redirect('/oficina/materiales/transformacion', '/oficina/materiales/recetas');
Route::redirect('/oficina/materiales/existencias', '/oficina/existencias');
Route::view('/oficina/validacion', 'office.validation');
Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');
Route::view('/oficina/prefrio', 'office.precooling');
Route::view('/oficina/gerencia', 'office.management');
Route::redirect('/oficina/existencias', '/oficina/materiales/exportaciones');
Route::view('/oficina/frigorifico/existencias', 'office.inventory-exports', [
    'inventoryType' => 'producto-terminado',
    'inventoryDomain' => 'frigorifico',
    'inventoryOffice' => 'existencias-pt',
    'inventoryContext' => 'FRIGORÍFICO · PT',
    'inventoryArea' => 'Producto terminado',
    'inventoryTitle' => 'Existencia de producto terminado',
    'inventoryDescription' => 'Folios activos desde Validación y Prefrío hasta cámara, carga y despacho.',
    'inventoryIcon' => '◇',
]);
Route::view('/oficina/materiales/exportaciones', 'office.inventory-exports', [
    'inventoryType' => 'materiales',
    'inventoryDomain' => 'materiales',
    'inventoryOffice' => 'exportaciones',
    'inventoryContext' => 'MATERIALES',
    'inventoryArea' => 'Materiales',
    'inventoryTitle' => 'Existencia de materiales',
    'inventoryDescription' => 'Saldos por folio, almacén, centro de costo, reserva, disponibilidad y ubicación.',
    'inventoryIcon' => '▦',
]);
Route::view('/oficina/materia-prima/existencias', 'office.inventory-exports', [
    'inventoryType' => 'materia-prima',
    'inventoryDomain' => 'materia-prima',
    'inventoryOffice' => 'existencias-mp',
    'inventoryContext' => 'MATERIA PRIMA',
    'inventoryArea' => 'Materia prima',
    'inventoryTitle' => 'Existencia de materia prima',
    'inventoryDescription' => 'Lotes vigentes, condición de hidrocooler, cámara asignada y continuidad hacia proceso.',
    'inventoryIcon' => '⌁',
]);
Route::view('/oficina/romana', 'office.weighbridge');
Route::view('/oficina/envases/cuenta-corriente', 'office.container-accounts');
Route::view('/oficina/envases/despachos', 'office.container-dispatches');
Route::view('/oficina/materia-prima', 'office.raw-material');
Route::view('/oficina/consultas', 'office.queries', [
    'navigationOffice' => 'busqueda',
    'queriesSection' => 'busqueda',
]);
Route::view('/oficina/consultas/sag', 'office.queries', [
    'navigationOffice' => 'sag',
    'queriesSection' => 'sag',
]);
Route::view('/oficina/consultas/productores', 'office.queries', [
    'navigationOffice' => 'productores',
    'queriesSection' => 'productores',
]);
Route::view('/oficina/materia-prima/lotes', 'office.raw-material');
Route::view('/oficina/materia-prima/fruta-a-proceso', 'office.raw-material-process');
Route::redirect('/oficina/materia-prima/romana', '/oficina/romana');
Route::redirect('/oficina/materia-prima/envases', '/oficina/envases/cuenta-corriente');
