<?php

namespace App\Providers;

use App\Enums\ContenidoCamara;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $alcance = app(AlcanceOperacionalUsuario::class);

        Gate::define(
            'consultar-configuracion-camaras',
            fn (User $usuario): bool => $alcance->puedeAccederOficina($usuario),
        );
        Gate::define(
            'crear-camaras-productos',
            fn (User $usuario): bool => $alcance->puedeCrearCamara(
                $usuario,
                ContenidoCamara::Productos,
            ),
        );
        Gate::define(
            'crear-camaras-materiales',
            fn (User $usuario): bool => $alcance->puedeCrearCamara(
                $usuario,
                ContenidoCamara::Materiales,
            ),
        );
        Gate::define(
            'operar-camaras-productos',
            fn (User $usuario): bool => $alcance->puedeOperarCamara(
                $usuario,
                ContenidoCamara::Productos,
            ),
        );
        Gate::define(
            'operar-camaras-materiales',
            fn (User $usuario): bool => $alcance->puedeOperarCamara(
                $usuario,
                ContenidoCamara::Materiales,
            ),
        );
        Gate::define(
            'supervisar-camaras-productos',
            fn (User $usuario): bool => $alcance->puedeSupervisarCamara(
                $usuario,
                ContenidoCamara::Productos,
            ),
        );
        Gate::define(
            'supervisar-camaras-materiales',
            fn (User $usuario): bool => $alcance->puedeSupervisarCamara(
                $usuario,
                ContenidoCamara::Materiales,
            ),
        );

        Gate::define(
            'administrar-camaras',
            fn (User $usuario): bool => $alcance->puedeAdministrarCamaras($usuario),
        );

        Gate::define(
            'administrar-accesos',
            fn (User $usuario): bool => $alcance->puedeAdministrarAccesos($usuario),
        );

        Gate::define(
            'gestionar-cargas',
            fn (User $usuario): bool => $alcance->puedeGestionarCargas($usuario),
        );

        Gate::define(
            'consultar-cargas-operacion',
            fn (User $usuario): bool => $alcance->puedeConsultarCargas($usuario),
        );

        Gate::define(
            'administrar-catalogos-materiales',
            fn (User $usuario): bool => $alcance->puedeAdministrarAccesos($usuario),
        );

        Gate::define(
            'gestionar-despachos-materiales',
            fn (User $usuario): bool => $alcance->puedeGestionarDespachosMateriales($usuario),
        );

        Gate::define(
            'consultar-despachos-materiales',
            fn (User $usuario): bool => $alcance->puedeConsultarDespachosMateriales($usuario),
        );

        Gate::define(
            'retirar-materiales',
            fn (User $usuario): bool => $alcance->puedeRetirarMateriales($usuario),
        );

        Gate::define(
            'cancelar-despachos-materiales',
            fn (User $usuario): bool => $alcance->puedeCancelarDespachosMateriales($usuario),
        );

        Gate::define(
            'consultar-kardex-materiales',
            fn (User $usuario): bool => $alcance->puedeConsultarKardexMateriales($usuario),
        );

        Sanctum::authenticateAccessTokensUsing(
            function (PersonalAccessToken $token, bool $esValido): bool {
                if (! $esValido || ! $token->tokenable instanceof User) {
                    return false;
                }

                if (! $token->tokenable->activo) {
                    return false;
                }

                if ($token->dispositivo_id === null) {
                    return in_array('oficina', $token->abilities ?? [], true);
                }

                return $token->dispositivo()->where('activo', true)->exists();
            },
        );
    }
}
