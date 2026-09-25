<?php

namespace App\Enums;

/**
 * Productiva: operación real, con fechas y prefijo documental.
 * Prueba: se creó para ensayos; no se activa, no aparece en reportes y nunca
 * alimenta saldos ni traspasos. Su historial de Materiales se conserva.
 */
enum TipoTemporada: string
{
    case Productiva = 'productiva';
    case Prueba = 'prueba';
}
