<?php

namespace App\Enums;

enum AccionResolucionDiscrepancia: string
{
    case ReanudarManiobra = 'reanudar_maniobra';
    case ReplanificarSufijo = 'replanificar_sufijo';
    case RetornoSeguro = 'retorno_seguro';
    case CancelarManiobra = 'cancelar_maniobra';
}
