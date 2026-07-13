<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
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

        Sanctum::authenticateAccessTokensUsing(
            function (PersonalAccessToken $token, bool $esValido): bool {
                if (! $esValido || ! $token->tokenable instanceof User) {
                    return false;
                }

                return $token->tokenable->activo
                    && $token->dispositivo_id !== null
                    && $token->dispositivo()->where('activo', true)->exists();
            },
        );
    }
}
