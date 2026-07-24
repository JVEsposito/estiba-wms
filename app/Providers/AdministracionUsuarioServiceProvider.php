<?php

namespace App\Providers;

use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Autorizacion\AlcanceOperacionalUsuarioMateriales;
use Illuminate\Support\ServiceProvider;

class AdministracionUsuarioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            AlcanceOperacionalUsuario::class,
            AlcanceOperacionalUsuarioMateriales::class,
        );
    }
}
