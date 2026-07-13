<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CondicionSag;
use Illuminate\Http\JsonResponse;

class CondicionSagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CondicionSag::query()
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }
}
