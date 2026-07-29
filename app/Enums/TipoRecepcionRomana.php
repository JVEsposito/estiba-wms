<?php

namespace App\Enums;

enum TipoRecepcionRomana: string
{
    case FrutaConEnvases = 'fruta_con_envases';
    case FrutaPesajeEnvases = 'fruta_pesaje_envases';
    case SoloEnvases = 'solo_envases';

    public function contieneFruta(): bool
    {
        return $this !== self::SoloEnvases;
    }
}
