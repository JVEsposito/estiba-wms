<?php

namespace App\Http\Middleware;

use App\Models\Contracts\PerteneceATemporada;
use App\Services\Temporadas\GuardiaTemporadaActiva;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechaza cualquier escritura sobre un registro que llega por la ruta y no
 * pertenece a la temporada activa.
 *
 * Corre después de SubstituteBindings (ver bootstrap/app.php), cuando los
 * parámetros ya son modelos. Las consultas (GET/HEAD) no se controlan: el
 * historial de temporadas anteriores sigue disponible para leer.
 */
class AsegurarTemporadaActivaDelRegistro
{
    public function __construct(private readonly GuardiaTemporadaActiva $guardia) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parametro) {
            if ($parametro instanceof PerteneceATemporada) {
                $this->guardia->asegurar($parametro);
            }
        }

        return $next($request);
    }
}
