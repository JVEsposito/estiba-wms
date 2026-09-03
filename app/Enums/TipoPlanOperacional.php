<?php

namespace App\Enums;

enum TipoPlanOperacional: string
{
    case RecepcionTunel = 'recepcion_tunel';
    case AlmacenamientoPallet = 'almacenamiento_pallet';
    case ConcentracionCarga = 'concentracion_carga';
    case PreparacionInspeccion = 'preparacion_inspeccion';
    case SegregacionRetenido = 'segregacion_retenido';
    case MovimientoOportunidad = 'movimiento_oportunidad';
    case ReordenamientoCamara = 'reordenamiento_camara';
    case DesocupacionCamara = 'desocupacion_camara';
    case EvacuacionEmergencia = 'evacuacion_emergencia';
    case DespachoDirecto = 'despacho_directo';
    case CorreccionDiscrepancia = 'correccion_discrepancia';
}
