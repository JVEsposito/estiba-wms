<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Services\Consultas\ServicioExpedienteFolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ConsultaFolioController extends Controller
{
    public function __invoke(
        Folio $folio,
        ServicioExpedienteFolio $servicio,
    ): JsonResponse {
        Gate::authorize('consultar-oficina-consultas');

        return response()->json($servicio->obtener($folio));
    }
}
