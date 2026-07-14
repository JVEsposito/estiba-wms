<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Enums\RolUsuario;
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

        Gate::define(
            'configurar-camaras',
            fn (User $usuario): bool => $usuario->activo
                && in_array($usuario->rol, [
                    RolUsuario::Administrador,
                    RolUsuario::Supervisor,
                ], true),
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
                    return $token->can('oficina');
                }

                return $token->dispositivo()->where('activo', true)->exists();
            },
        );
    }
}
