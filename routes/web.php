<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/oficina/camaras', 'office.cameras');
Route::view('/oficina/cargas', 'office.loads');
Route::view('/oficina/accesos', 'office.accesses');
