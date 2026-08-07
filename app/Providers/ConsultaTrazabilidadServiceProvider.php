<?php

namespace App\Providers;

use App\Http\Controllers\Api\ConsultaFolioController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ConsultaTrazabilidadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'can:consultar-oficina-consultas'])
            ->get('/api/consultas/folios/{folio}', ConsultaFolioController::class);
    }
}
