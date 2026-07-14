<?php

use App\Exceptions\AdvertenciasMovimientoPendientes;
use App\Exceptions\ConflictoOperacion;
use App\Exceptions\OperacionNoAutorizada;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (
            AdvertenciasMovimientoPendientes $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => 'confirmacion_requerida',
                'advertencias' => $exception->advertencias,
            ], 409);
        });

        $exceptions->render(function (
            ConflictoOperacion $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => 'conflicto_operacional',
            ], 409);
        });

        $exceptions->render(function (
            OperacionNoAutorizada $exception,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => 'operacion_no_autorizada',
            ], 403);
        });

        $exceptions->render(function (DomainException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => 'regla_de_negocio',
            ], 422);
        });
    })->create();
