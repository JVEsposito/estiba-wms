<?php

namespace App\Enums;

/**
 * Tipos de registro que deben quedar cerrados antes de dar por terminada una
 * temporada. El orden de los casos es el orden recomendado de resolución:
 * primero lo que ocupa fruta o procesos, al final los folios PT, que se
 * regularizan solo cuando ya no participan en ningún proceso abierto.
 */
enum CategoriaPendienteCierre: string
{
    case RecepcionesRomana = 'recepciones_romana';
    case ValidacionMp = 'validacion_mp';
    case LotesMp = 'lotes_mp';
    case Hidrocooler = 'hidrocooler';
    case Prefrio = 'prefrio';
    case InspeccionSag = 'inspeccion_sag';
    case Embarques = 'embarques';
    case Cargas = 'cargas';
    case PlanesOperacionales = 'planes_operacionales';
    case SesionesEstiba = 'sesiones_estiba';
    case FoliosPt = 'folios_pt';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RecepcionesRomana => 'Recepciones de Romana abiertas',
            self::ValidacionMp => 'Validaciones MP sin confirmar',
            self::LotesMp => 'Lotes MP abiertos',
            self::Hidrocooler => 'Hidrocooler en curso',
            self::Prefrio => 'Procesos de prefrío activos',
            self::InspeccionSag => 'Inspecciones SAG abiertas',
            self::Embarques => 'Embarques sin cerrar',
            self::Cargas => 'Cargas sin cerrar',
            self::PlanesOperacionales => 'Planes de estiba abiertos',
            self::SesionesEstiba => 'Sesiones de estiba abiertas',
            self::FoliosPt => 'Pallets y saldos PT sin despachar',
        };
    }

    /** La etiqueta dentro de una frase: minúscula inicial, conserva siglas y nombres. */
    public function enFrase(): string
    {
        $etiqueta = $this->etiqueta();

        return mb_strtolower(mb_substr($etiqueta, 0, 1)).mb_substr($etiqueta, 1);
    }

    /** Dónde se cierra el registro de la forma normal. */
    public function comoCerrar(): string
    {
        return match ($this) {
            self::RecepcionesRomana => 'Registrar la salida del camión en Romana.',
            self::ValidacionMp => 'Confirmar la validación en Validación MP.',
            self::LotesMp => 'Entregar el lote a proceso o anularlo en Materia Prima.',
            self::Hidrocooler => 'Registrar el término del proceso en Hidrocooler.',
            self::Prefrio => 'Finalizar o cancelar el proceso en Prefrío.',
            self::InspeccionSag => 'Registrar el resultado o cancelar el lote en Inspección SAG.',
            self::Embarques => 'Confirmar con su carga o cancelar el embarque en el calendario.',
            self::Cargas => 'Registrar la salida del camión o cancelar la carga.',
            self::PlanesOperacionales => 'Completar o cancelar el plan en Cámaras.',
            self::SesionesEstiba => 'Cerrar la sesión en la tablet o con cierre forzado.',
            self::FoliosPt => 'Despacharlo en una carga, anular su validación o consolidarlo en una repa.',
        };
    }

    /** Qué hace la regularización administrativa, además de dejar el registro auditado. */
    public function efectoRegularizacion(): string
    {
        return match ($this) {
            self::FoliosPt => 'El folio queda retirado definitivamente y libera su posición en la cámara.',
            self::SesionesEstiba => 'La sesión se cierra forzosamente y libera la cámara.',
            self::Prefrio => 'El proceso se cancela con un evento auditado; sus folios quedan retenidos si el ciclo comenzó y el túnel se libera.',
            self::Hidrocooler => 'El ciclo se cancela sin inventar mediciones finales, libera el equipo y devuelve el lote a pendiente de hidrocooler.',
            default => 'El registro queda marcado como regularizado, sin cambiar su estado.',
        };
    }

    /** Las sesiones de estiba pertenecen a una cámara, no a una temporada. */
    public function soloTemporadaActiva(): bool
    {
        return $this === self::SesionesEstiba;
    }

    /** @return list<self> Procesos que deben quedar resueltos antes de regularizar folios PT. */
    public static function procesosDeFolios(): array
    {
        return [self::Prefrio, self::InspeccionSag, self::Cargas, self::PlanesOperacionales];
    }
}
