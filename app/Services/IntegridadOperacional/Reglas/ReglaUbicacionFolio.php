<?php

namespace App\Services\IntegridadOperacional\Reglas;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\HabilitacionAlmacenamientoFolio;
use App\Enums\SeveridadHallazgoIntegridadOperacional;
use App\Enums\TipoBulto;
use App\Services\IntegridadOperacional\HallazgoIntegridadDetectado;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReglaUbicacionFolio implements ReglaIntegridadOperacional
{
    public const CODIGO = 'ubicacion_folio_inconsistente';

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function nombre(): string
    {
        return 'Coherencia entre folio y ubicación actual';
    }

    public function modulo(): string
    {
        return 'camaras';
    }

    public function evaluar(): iterable
    {
        $hallazgos = [];
        $terminales = [
            EstadoOperacionalFolio::Anulado->value,
            EstadoOperacionalFolio::RetiradoDefinitivo->value,
            EstadoOperacionalFolio::Despachado->value,
            EstadoOperacionalFolio::Agotado->value,
        ];

        $noOperables = DB::table('ubicaciones_actuales as ubicacion')
            ->join('folios as folio', 'folio.id', '=', 'ubicacion.folio_id')
            ->join('camaras as camara', 'camara.id', '=', 'ubicacion.camara_id')
            ->leftJoin('posiciones as posicion', 'posicion.id', '=', 'ubicacion.posicion_id')
            ->leftJoin('folios_materiales as material', 'material.folio_id', '=', 'folio.id')
            ->where(function (Builder $consulta) use ($terminales): void {
                $consulta->where('folio.activo', false)
                    ->orWhereIn('folio.estado_operacional', $terminales);
            })
            ->orderBy('folio.numero_folio')
            ->get([
                'folio.id as folio_id',
                'folio.numero_folio',
                'folio.activo',
                'folio.estado_operacional',
                'camara.codigo as camara_codigo',
                'posicion.etiqueta as posicion_etiqueta',
                'material.folio_id as folio_material_id',
            ]);

        foreach ($noOperables as $fila) {
            $hallazgos[] = new HallazgoIntegridadDetectado(
                reglaCodigo: self::CODIGO,
                severidad: SeveridadHallazgoIntegridadOperacional::Critico,
                modulo: $fila->folio_material_id ? 'materiales' : $this->modulo(),
                entidadTipo: 'folio',
                entidadId: $fila->folio_id,
                referencia: $fila->numero_folio,
                titulo: 'Folio no operable conserva ubicación actual',
                detalle: sprintf(
                    'El folio %s está %s y todavía figura en %s%s.',
                    $fila->numero_folio,
                    $fila->estado_operacional,
                    $fila->camara_codigo,
                    $fila->posicion_etiqueta ? " / {$fila->posicion_etiqueta}" : '',
                ),
                contexto: [
                    'activo' => (bool) $fila->activo,
                    'estado_operacional' => $fila->estado_operacional,
                    'camara' => $fila->camara_codigo,
                    'posicion' => $fila->posicion_etiqueta,
                ],
            );
        }

        $sinHabilitacion = DB::table('ubicaciones_actuales as ubicacion')
            ->join('folios as folio', 'folio.id', '=', 'ubicacion.folio_id')
            ->join('camaras as camara', 'camara.id', '=', 'ubicacion.camara_id')
            ->leftJoin('posiciones as posicion', 'posicion.id', '=', 'ubicacion.posicion_id')
            ->leftJoin('folios_materiales as material', 'material.folio_id', '=', 'folio.id')
            ->whereNull('material.folio_id')
            ->where('folio.activo', true)
            ->whereIn('folio.tipo_bulto', [TipoBulto::Pallet->value, TipoBulto::Saldo->value])
            ->where(
                'folio.habilitacion_almacenamiento',
                HabilitacionAlmacenamientoFolio::NoHabilitado->value,
            )
            ->orderBy('folio.numero_folio')
            ->get([
                'folio.id as folio_id',
                'folio.numero_folio',
                'folio.estado_operacional',
                'folio.condicion_termica',
                'folio.habilitacion_almacenamiento',
                'camara.codigo as camara_codigo',
                'posicion.etiqueta as posicion_etiqueta',
            ]);

        foreach ($sinHabilitacion as $fila) {
            $hallazgos[] = new HallazgoIntegridadDetectado(
                reglaCodigo: self::CODIGO,
                severidad: SeveridadHallazgoIntegridadOperacional::Advertencia,
                modulo: $this->modulo(),
                entidadTipo: 'folio',
                entidadId: $fila->folio_id,
                referencia: $fila->numero_folio,
                titulo: 'Folio ubicado sin habilitación de almacenamiento',
                detalle: sprintf(
                    'El folio %s figura en %s%s con habilitación %s.',
                    $fila->numero_folio,
                    $fila->camara_codigo,
                    $fila->posicion_etiqueta ? " / {$fila->posicion_etiqueta}" : '',
                    $fila->habilitacion_almacenamiento ?? 'no registrada',
                ),
                contexto: [
                    'estado_operacional' => $fila->estado_operacional,
                    'condicion_termica' => $fila->condicion_termica,
                    'habilitacion_almacenamiento' => $fila->habilitacion_almacenamiento,
                    'camara' => $fila->camara_codigo,
                    'posicion' => $fila->posicion_etiqueta,
                ],
            );
        }

        return $hallazgos;
    }
}
