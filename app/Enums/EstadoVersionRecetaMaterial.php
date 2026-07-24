<?php

namespace App\Enums;

enum EstadoVersionRecetaMaterial: string
{
    case Borrador = 'borrador';
    case Activa = 'activa';
    case Retirada = 'retirada';
}
