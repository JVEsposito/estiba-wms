<?php

namespace App\Enums;

enum AccionResolucionDiscrepancia: string
{
    case ReanudarManiobra = 'reanudar_maniobra';
    case CancelarManiobra = 'cancelar_maniobra';
}
