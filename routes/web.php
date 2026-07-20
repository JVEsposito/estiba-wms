<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/oficina/camaras', 'office.cameras');
Route::view('/oficina/cargas', 'office.loads');
Route::view('/oficina/accesos', 'office.accesses');
Route::view('/oficina/materiales', 'office.materials');
Route::view('/oficina/validacion', 'office.validation');
Route::view('/oficina/validacion/catalogo', 'office.validation-catalog');
Route::view('/oficina/prefrio', 'office.precooling');
