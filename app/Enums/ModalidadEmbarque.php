<?php

namespace App\Enums;

enum ModalidadEmbarque: string
{
    case Maritimo = 'maritimo';
    case Aereo = 'aereo';
    case Terrestre = 'terrestre';
    case PorConfirmar = 'por_confirmar';
}
