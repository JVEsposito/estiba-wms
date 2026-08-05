<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ImportacionRecepcionesMaterialesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/materiales-recepciones-importacion.php'));
    }
}
