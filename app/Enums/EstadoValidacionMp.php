<?php

namespace App\Enums;

enum EstadoValidacionMp: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Validada = 'validada';
}
