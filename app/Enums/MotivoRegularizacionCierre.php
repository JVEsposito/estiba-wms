<?php

namespace App\Enums;

enum MotivoRegularizacionCierre: string
{
    case DespachadoSinRegistro = 'despachado_sin_registro';
    case MermaAnulacion = 'merma_anulacion';
    case ErrorDigitacion = 'error_digitacion';
    case DatoPrueba = 'dato_prueba';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DespachadoSinRegistro => 'Despachado sin registro',
            self::MermaAnulacion => 'Merma o anulación',
            self::ErrorDigitacion => 'Error de digitación',
            self::DatoPrueba => 'Dato de prueba',
        };
    }
}
