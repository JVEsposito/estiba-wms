<?php

namespace App\Enums;

enum EstadoSubloteRetornoPacking: string
{
    case PendienteUbicacion = 'pendiente_ubicacion';
    case UbicadoCamara = 'ubicado_camara';
    case Anulado = 'anulado';
}
