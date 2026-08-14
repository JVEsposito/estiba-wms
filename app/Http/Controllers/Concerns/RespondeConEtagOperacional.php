<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\Response;

trait RespondeConEtagOperacional
{
    protected function conEtagOperacional(Response $respuesta, string $etag): Response
    {
        $respuesta->setEtag($etag);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-cache');
        $respuesta->setVary('Authorization');
        $respuesta->headers->set('Access-Control-Expose-Headers', 'ETag');

        return $respuesta;
    }
}
