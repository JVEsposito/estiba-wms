<?php

namespace App\Providers;

use App\Enums\ContenidoCamara;
use App\Events\EventoCargaRegistrado;
use App\Listeners\CrearNotificacionesOperacionales;
use App\Models\EventoCarga;
use App\Models\PersonalAccessToken;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Observers\EventoCargaObserver;
use App\Observers\UbicacionActualObserver;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Support\Facades\Event;
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
        if ($this->app->environment('local') &&
            class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        EventoCarga::observe(EventoCargaObserver::class);
        UbicacionActual::observe(UbicacionActualObserver::class);
        Event::listen(EventoCargaRegistrado::class, CrearNotificacionesOperacionales::class);

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
            'consultar-catalogo-cargas',
            fn (User $usuario): bool => $alcance->puedeConsultarCatalogoCargas($usuario),
        );
        Gate::define(
            'gestionar-andenes',
            fn (User $usuario): bool => $alcance->puedeGestionarAndenes($usuario),
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
        Gate::define(
            'corregir-items-estibados-materiales',
            fn (User $usuario): bool => $alcance->puedeCorregirItemsEstibadosMateriales($usuario),
        );
        Gate::define(
            'consultar-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeConsultarDespachosMateriales($usuario),
        );
        Gate::define(
            'gestionar-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeCorregirItemsEstibadosMateriales($usuario),
        );
        Gate::define(
            'anular-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeCorregirItemsEstibadosMateriales($usuario),
        );
        Gate::define(
            'validar-pallets',
            fn (User $usuario): bool => $alcance->puedeValidarPallets($usuario),
        );
        Gate::define(
            'rechazar-pallets',
            fn (User $usuario): bool => $alcance->puedeRechazarPallets($usuario),
        );
        Gate::define(
            'consultar-validaciones-pallet',
            fn (User $usuario): bool => $alcance->puedeConsultarValidacionesPallet($usuario),
        );
        Gate::define(
            'administrar-catalogos-validacion',
            fn (User $usuario): bool => $alcance->puedeAdministrarCatalogosValidacion($usuario),
        );
        Gate::define(
            'consultar-prefrio',
            fn (User $usuario): bool => $alcance->puedeConsultarPrefrio($usuario),
        );
        Gate::define(
            'operar-prefrio',
            fn (User $usuario): bool => $alcance->puedeOperarPrefrio($usuario),
        );
        Gate::define(
            'supervisar-prefrio',
            fn (User $usuario): bool => $alcance->puedeSupervisarPrefrio($usuario),
        );
        Gate::define(
            'administrar-tuneles-prefrio',
            fn (User $usuario): bool => $alcance->puedeAdministrarTunelesPrefrio($usuario),
        );
        Gate::define(
            'consultar-panel-gerencial',
            fn (User $usuario): bool => $alcance->puedeConsultarPanelGerencial($usuario),
        );
        Gate::define(
            'consultar-romana',
            fn (User $usuario): bool => $alcance->puedeConsultarRomana($usuario),
        );
        Gate::define(
            'operar-romana',
            fn (User $usuario): bool => $alcance->puedeOperarRomana($usuario),
        );
        Gate::define(
            'validar-mp',
            fn (User $usuario): bool => $alcance->puedeValidarMp($usuario),
        );
        Gate::define(
            'consultar-cuenta-envases',
            fn (User $usuario): bool => $alcance->puedeConsultarCuentaEnvases($usuario),
        );
        Gate::define(
            'revisar-cuenta-envases',
            fn (User $usuario): bool => $alcance->puedeRevisarCuentaEnvases($usuario),
        );
        Gate::define(
            'gestionar-despacho-envases',
            fn (User $usuario): bool => $alcance->puedeGestionarDespachoEnvases($usuario),
        );
        Gate::define(
            'anular-despacho-envases',
            fn (User $usuario): bool => $alcance->puedeAnularDespachoEnvases($usuario),
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
