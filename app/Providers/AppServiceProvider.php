<?php

namespace App\Providers;

use App\Enums\RolUsuario;
use App\Models\PersonalAccessToken;
use App\Models\User;
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

        Gate::define(
            'administrar-camaras',
            fn (User $usuario): bool => $usuario->activo
                && $usuario->rol === RolUsuario::Administrador,
        );

        Gate::define(
            'administrar-accesos',
            fn (User $usuario): bool => $usuario->activo
                && $usuario->rol === RolUsuario::Administrador,
        );

        Gate::define(
            'gestionar-cargas',
            fn (User $usuario): bool => $usuario->activo
                && in_array($usuario->rol, [
                    RolUsuario::Administrador,
                    RolUsuario::Supervisor,
                    RolUsuario::Despachador,
                ], true),
        );

        Gate::define(
            'consultar-cargas-operacion',
            fn (User $usuario): bool => $usuario->activo
                && in_array($usuario->rol, [
                    RolUsuario::Administrador,
                    RolUsuario::Supervisor,
                    RolUsuario::Despachador,
                    RolUsuario::Operador,
                    RolUsuario::Consulta,
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
                    return in_array('oficina', $token->abilities ?? [], true);
                }

                return $token->dispositivo()->where('activo', true)->exists();
            },
        );
    }
}
