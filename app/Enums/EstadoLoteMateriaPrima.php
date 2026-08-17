<?php

namespace App\Enums;

enum EstadoLoteMateriaPrima: string
{
    case Borrador = 'borrador';
    case PendienteHidrocooler = 'pendiente_hidrocooler';
    case HidrocoolerEnCurso = 'hidrocooler_en_curso';
    case PendienteAsignacion = 'pendiente_asignacion';
    case DisponibleProceso = 'disponible_proceso';
    case AsignadoCamara = 'asignado_camara';
    case EntregaParcialProceso = 'entrega_parcial_proceso';
    case EntregadoProceso = 'entregado_proceso';
    case Anulado = 'anulado';

    public function esEditable(): bool
    {
        return $this === self::Borrador;
    }
}
