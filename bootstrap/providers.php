<?php

use App\Providers\AdministracionUsuarioServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CustodiaDistribuidaMaterialesServiceProvider;

return [
    AppServiceProvider::class,
    AdministracionUsuarioServiceProvider::class,
    CustodiaDistribuidaMaterialesServiceProvider::class,
];
