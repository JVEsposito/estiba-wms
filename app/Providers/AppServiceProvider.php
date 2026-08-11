<?php

namespace App\Providers;

use App\Enums\ContenidoCamara;
use App\Enums\RolUsuario;
use App\Events\EventoCargaRegistrado;
use App\Listeners\CrearNotificacionesOperacionales;
use App\Models\EventoCarga;
use App\Models\PersonalAccessToken;
use App\Models\UbicacionActual;
use App\Models\User;
use App\Observers\EventoCargaObserver;
use App\Observers\InvalidarPanelGerencialObserver;
use App\Observers\UbicacionActualObserver;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for(
            'existencias-cortes',
            fn (Request $request): Limit => Limit::perMinute(3)->by(
                'existencias:cortes:usuario:'.($request->user()?->id ?? $request->ip()),
            ),
        );
        RateLimiter::for('existencias-consultas', function (Request $request): array {
            $token = trim((string) $request->query('token', ''));
            $identificador = $token !== '' ? hash('sha256', $token) : 'sin-token';

            return [
                Limit::perMinute(6)->by('existencias:consultas:token:'.$identificador),
                Limit::perMinute(30)->by('existencias:consultas:ip:'.$request->ip()),
            ];
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        EventoCarga::observe(EventoCargaObserver::class);
        UbicacionActual::observe(UbicacionActualObserver::class);
        foreach (InvalidarPanelGerencialObserver::modelosObservados() as $modelo) {
            $modelo::observe(InvalidarPanelGerencialObserver::class);
        }
        Event::listen(EventoCargaRegistrado::class, CrearNotificacionesOperacionales::class);

        $alcance = app(AlcanceOperacionalUsuario::class);

        Gate::define(
            'consultar-configuracion-camaras',
            fn (User $usuario): bool => $alcance->puedeConsultarConfiguracionCamaras($usuario),
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
            'crear-camaras-materia-prima',
            fn (User $usuario): bool => $alcance->puedeCrearCamara(
                $usuario,
                ContenidoCamara::MateriaPrima,
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
            'consultar-accesos',
            fn (User $usuario): bool => $alcance->puedeConsultarAccesos($usuario),
        );
        Gate::define(
            'reiniciar-datos-operacionales',
            fn (User $usuario): bool => $usuario->activo
                && $usuario->rol === RolUsuario::Administrador,
        );
        Gate::define(
            'gestionar-cargas',
            fn (User $usuario): bool => $alcance->puedeGestionarCargas($usuario),
        );
        Gate::define(
            'autorizar-sobrecupo-embarques',
            fn (User $usuario): bool => $alcance->puedeAutorizarSobrecupoEmbarques($usuario),
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
            'gestionar-bloqueos-materiales',
            fn (User $usuario): bool => $alcance->puedeGestionarBloqueosMateriales($usuario),
        );
        Gate::define(
            'consultar-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeConsultarRecepcionesMateriales($usuario),
        );
        Gate::define(
            'gestionar-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeGestionarRecepcionesMateriales($usuario),
        );
        Gate::define(
            'anular-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeAnularRecepcionesMateriales($usuario),
        );
        Gate::define(
            'administrar-recepciones-materiales',
            fn (User $usuario): bool => $alcance->puedeAdministrarRecepcionesMateriales($usuario),
        );
        Gate::define(
            'imprimir-etiquetas-materiales',
            fn (User $usuario): bool => $alcance->puedeImprimirEtiquetasMateriales($usuario),
        );
        Gate::define(
            'consultar-transformaciones-materiales',
            fn (User $usuario): bool => $alcance->puedeConsultarTransformacionesMateriales($usuario),
        );
        Gate::define(
            'gestionar-transformaciones-materiales',
            fn (User $usuario): bool => $alcance->puedeGestionarTransformacionesMateriales($usuario),
        );
        Gate::define(
            'operar-transformaciones-materiales',
            fn (User $usuario): bool => $alcance->puedeOperarTransformacionesMateriales($usuario),
        );
        Gate::define(
            'revertir-transformaciones-materiales',
            fn (User $usuario): bool => $alcance->puedeRevertirTransformacionesMateriales($usuario),
        );
        Gate::define(
            'administrar-recetas-materiales',
            fn (User $usuario): bool => $alcance->puedeAdministrarRecetasMateriales($usuario),
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
            'corregir-validaciones-pallet',
            fn (User $usuario): bool => $alcance->puedeCorregirValidacionesPallet($usuario),
        );
        Gate::define(
            'administrar-catalogos-validacion',
            fn (User $usuario): bool => $alcance->puedeAdministrarCatalogosValidacion($usuario),
        );
        Gate::define(
            'consultar-catalogos-validacion',
            fn (User $usuario): bool => $alcance->puedeConsultarCatalogosValidacion($usuario),
        );
        Gate::define(
            'consultar-prefrio',
            fn (User $usuario): bool => $alcance->puedeConsultarPrefrio($usuario),
        );
        Gate::define(
            'consultar-inspeccion-sag',
            fn (User $usuario): bool => $alcance->puedeConsultarInspeccionSag($usuario),
        );
        Gate::define(
            'gestionar-inspeccion-sag',
            fn (User $usuario): bool => $alcance->puedeGestionarInspeccionSag($usuario),
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
            'corregir-procesos-prefrio',
            fn (User $usuario): bool => $usuario->activo
                && $usuario->rol === RolUsuario::Administrador,
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
            'corregir-recepciones-romana',
            fn (User $usuario): bool => $alcance->puedeCorregirRecepcionesRomana($usuario),
        );
        Gate::define(
            'validar-mp',
            fn (User $usuario): bool => $alcance->puedeValidarMp($usuario),
        );
        Gate::define(
            'consultar-materia-prima',
            fn (User $usuario): bool => $alcance->puedeConsultarMateriaPrima($usuario),
        );
        Gate::define(
            'gestionar-lotes-materia-prima',
            fn (User $usuario): bool => $alcance->puedeGestionarLotesMateriaPrima($usuario),
        );
        Gate::define(
            'supervisar-lotes-materia-prima',
            fn (User $usuario): bool => $alcance->puedeSupervisarLotesMateriaPrima($usuario),
        );
        Gate::define(
            'consultar-fruta-proceso',
            fn (User $usuario): bool => $alcance->puedeConsultarFrutaProceso($usuario),
        );
        Gate::define(
            'entregar-fruta-proceso',
            fn (User $usuario): bool => $alcance->puedeEntregarFrutaProceso($usuario),
        );
        Gate::define(
            'anular-entregas-fruta-proceso',
            fn (User $usuario): bool => $alcance->puedeEntregarFrutaProceso($usuario)
                || $alcance->puedeCorregirEntregasFrutaProceso($usuario),
        );
        Gate::define(
            'consultar-oficina-consultas',
            fn (User $usuario): bool => $alcance->puedeConsultarOficinaConsultas($usuario),
        );
        Gate::define(
            'consultar-sag',
            fn (User $usuario): bool => $alcance->puedeConsultarSag($usuario),
        );
        Gate::define(
            'asociar-productores-csg',
            fn (User $usuario): bool => $alcance->puedeAsociarProductoresCsg($usuario),
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
