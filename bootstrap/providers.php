<?php

use App\Providers\AdministracionUsuarioServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CustodiaDistribuidaMaterialesServiceProvider;
use App\Providers\ImportacionRecepcionesMaterialesServiceProvider;

return [
    AppServiceProvider::class,
    AdministracionUsuarioServiceProvider::class,
    CustodiaDistribuidaMaterialesServiceProvider::class,
    ImportacionRecepcionesMaterialesServiceProvider::class,
];
