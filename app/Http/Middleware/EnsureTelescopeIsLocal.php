<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureTelescopeIsLocal
{
    /**
     * Restrict the diagnostic dashboard to the computer running Laravel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
