<?php

namespace App\Enums;

enum MotivoValidacionPallet: string
{
    case CondicionFruta = 'condicion_fruta';
    case FolioIlegible = 'folio_ilegible';
    case FolioDuplicado = 'folio_duplicado';
    case EtiquetaNoCoincide = 'etiqueta_no_coincide';
    case ClienteMarcaIncorrectos = 'cliente_marca_incorrectos';
    case CsgNoCoincide = 'csg_no_coincide';
    case EspecieVariedadIncorrectas = 'especie_variedad_incorrectas';
    case EnvaseIncorrecto = 'envase_incorrecto';
    case CantidadCajasIncorrecta = 'cantidad_cajas_incorrecta';
    case DanoFisicoPallet = 'dano_fisico_pallet';
    case CombinacionNoHabilitada = 'combinacion_no_habilitada';
    case Otro = 'otro';
}
