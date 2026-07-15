<?php

namespace App\Enums;

enum TipoBulto: string
{
    case Pallet = 'pallet';
    case Saldo = 'saldo';
    case Material = 'material';
}
