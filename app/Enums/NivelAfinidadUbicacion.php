<?php

namespace App\Enums;

enum NivelAfinidadUbicacion: string
{
    case ClienteMarcaFormato = 'cliente_marca_formato';
    case ClienteMarca = 'cliente_marca';
    case Cliente = 'cliente';
    case BandaLibre = 'banda_libre';
    case SinAfinidad = 'sin_afinidad';
}
