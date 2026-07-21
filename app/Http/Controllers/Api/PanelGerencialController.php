<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gerencia\ServicioPanelGerencial;
use Illuminate\Http\JsonResponse;

class PanelGerencialController extends Controller
{
    public function __invoke(ServicioPanelGerencial $servicio): JsonResponse
    {
        return response()
            ->json(['data' => $servicio->obtener()])
            ->header('Cache-Control', 'no-store, private');
    }
}
