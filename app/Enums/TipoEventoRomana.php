<?php

namespace App\Enums;

enum TipoEventoRomana: string
{
    case IngresoRegistrado = 'ingreso_registrado';
    case IngresoActualizado = 'ingreso_actualizado';
    case IngresoConfirmado = 'ingreso_confirmado';
    case RecepcionCerrada = 'recepcion_cerrada';
}
