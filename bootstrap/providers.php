<?php

use App\Providers\AdministracionUsuarioServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CustodiaDistribuidaMaterialesServiceProvider;
use App\Providers\RepaletizajeServiceProvider;

return [
    AppServiceProvider::class,
    AdministracionUsuarioServiceProvider::class,
    CustodiaDistribuidaMaterialesServiceProvider::class,
    RepaletizajeServiceProvider::class,
];
