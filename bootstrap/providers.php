<?php

use App\Providers\AdministracionUsuarioServiceProvider;
use App\Providers\AnulacionValidacionPalletServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CustodiaDistribuidaMaterialesServiceProvider;
use App\Providers\RepaletizajeServiceProvider;

return [
    \App\Providers\ConsultaTrazabilidadServiceProvider::class,
    AppServiceProvider::class,
    AdministracionUsuarioServiceProvider::class,
    CustodiaDistribuidaMaterialesServiceProvider::class,
    RepaletizajeServiceProvider::class,
    AnulacionValidacionPalletServiceProvider::class,
];
