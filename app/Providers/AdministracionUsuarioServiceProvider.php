<?php

namespace App\Providers;

use App\Http\Controllers\Api\AdministracionAccesoController;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Autorizacion\AlcanceOperacionalUsuarioMateriales;
use Illuminate\Support\Facades\Route;
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

    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'can:administrar-accesos'])
            ->put(
                'api/administracion/usuarios/{usuario}',
                [AdministracionAccesoController::class, 'actualizarUsuario'],
            );
    }
}
