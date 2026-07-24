<?php

use App\Providers\AdministracionUsuarioServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\RecepcionMaterialServiceProvider;
use App\Providers\TransformacionMaterialServiceProvider;

return [
    AppServiceProvider::class,
    AdministracionUsuarioServiceProvider::class,
    RecepcionMaterialServiceProvider::class,
    TransformacionMaterialServiceProvider::class,
];
