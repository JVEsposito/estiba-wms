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
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        $alcance = app(AlcanceOperacionalUsuario::class);

        Gate::define('consultar-configuracion-camaras', fn (User $u): bool => $alcance->puedeAccederOficina($u));
        Gate::define('crear-camaras-productos', fn (User $u): bool => $alcance->puedeCrearCamara($u, ContenidoCamara::Productos));
        Gate::define('crear-camaras-materiales', fn (User $u): bool => $alcance->puedeCrearCamara($u, ContenidoCamara::Materiales));
        Gate::define('operar-camaras-productos', fn (User $u): bool => $alcance->puedeOperarCamara($u, ContenidoCamara::Productos));
        Gate::define('operar-camaras-materiales', fn (User $u): bool => $alcance->puedeOperarCamara($u, ContenidoCamara::Materiales));
        Gate::define('supervisar-camaras-productos', fn (User $u): bool => $alcance->puedeSupervisarCamara($u, ContenidoCamara::Productos));
        Gate::define('supervisar-camaras-materiales', fn (User $u): bool => $alcance->puedeSupervisarCamara($u, ContenidoCamara::Materiales));
        Gate::define('administrar-camaras', fn (User $u): bool => $alcance->puedeAdministrarCamaras($u));
        Gate::define('administrar-accesos', fn (User $u): bool => $alcance->puedeAdministrarAccesos($u));
        Gate::define('gestionar-cargas', fn (User $u): bool => $alcance->puedeGestionarCargas($u));
        Gate::define('consultar-cargas-operacion', fn (User $u): bool => $alcance->puedeConsultarCargas($u));
        Gate::define('consultar-catalogo-cargas', fn (User $u): bool => $alcance->puedeConsultarCatalogoCargas($u));
        Gate::define('gestionar-andenes', fn (User $u): bool => $alcance->puedeGestionarAndenes($u));
        Gate::define('administrar-catalogos-materiales', fn (User $u): bool => $alcance->puedeAdministrarAccesos($u));
        Gate::define('gestionar-despachos-materiales', fn (User $u): bool => $alcance->puedeGestionarDespachosMateriales($u));
        Gate::define('consultar-despachos-materiales', fn (User $u): bool => $alcance->puedeConsultarDespachosMateriales($u));
        Gate::define('retirar-materiales', fn (User $u): bool => $alcance->puedeRetirarMateriales($u));
        Gate::define('cancelar-despachos-materiales', fn (User $u): bool => $alcance->puedeCancelarDespachosMateriales($u));
        Gate::define('consultar-kardex-materiales', fn (User $u): bool => $alcance->puedeConsultarKardexMateriales($u));
        Gate::define('validar-pallets', fn (User $u): bool => $alcance->puedeValidarPallets($u));
        Gate::define('rechazar-pallets', fn (User $u): bool => $alcance->puedeRechazarPallets($u));
        Gate::define('consultar-validaciones-pallet', fn (User $u): bool => $alcance->puedeConsultarValidacionesPallet($u));

        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $esValido): bool {
            if (! $esValido || ! $token->tokenable instanceof User || ! $token->tokenable->activo) return false;
            if ($token->dispositivo_id === null) return in_array('oficina', $token->abilities ?? [], true);
            return $token->dispositivo()->where('activo', true)->exists();
        });
    }
}
