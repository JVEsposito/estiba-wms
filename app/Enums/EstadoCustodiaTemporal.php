<?php

namespace App\Enums;

enum EstadoCustodiaTemporal: string
{
    case Activa = 'activa';
    case ResueltaDestinoUtil = 'resuelta_destino_util';
    case ResueltaRetorno = 'resuelta_retorno';
}
