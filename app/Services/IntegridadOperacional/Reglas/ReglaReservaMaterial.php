<?php

namespace App\Services\IntegridadOperacional\Reglas;

use App\Enums\SeveridadHallazgoIntegridadOperacional;
use App\Services\IntegridadOperacional\HallazgoIntegridadDetectado;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReglaReservaMaterial implements ReglaIntegridadOperacional
{
    public const CODIGO = 'saldo_material_fuera_de_rango';

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function nombre(): string
    {
        return 'Cantidad y reserva de materiales dentro de rango';
    }

    public function modulo(): string
    {
        return 'materiales';
    }

    public function evaluar(): iterable
    {
        return DB::table('folios_materiales as material')
            ->join('folios as folio', 'folio.id', '=', 'material.folio_id')
            ->join('items_materiales as item', 'item.id', '=', 'material.item_material_id')
            ->where(function (Builder $consulta): void {
                $consulta
                    ->where('material.cantidad_actual', '<', 0)
                    ->orWhere('material.cantidad_reservada', '<', 0)
                    ->orWhereColumn('material.cantidad_reservada', '>', 'material.cantidad_actual');
            })
            ->orderBy('folio.numero_folio')
            ->get([
                'folio.id as folio_id',
                'folio.numero_folio',
                'item.codigo as item_codigo',
                'item.nombre as item_nombre',
                'material.cantidad_actual',
                'material.cantidad_reservada',
                'material.unidad_medida',
            ])
            ->map(fn (object $fila): HallazgoIntegridadDetectado => new HallazgoIntegridadDetectado(
                reglaCodigo: self::CODIGO,
                severidad: SeveridadHallazgoIntegridadOperacional::Critico,
                modulo: $this->modulo(),
                entidadTipo: 'folio_material',
                entidadId: $fila->folio_id,
                referencia: $fila->numero_folio,
                titulo: 'Saldo o reserva de material fuera de rango',
                detalle: sprintf(
                    'El folio %s de %s tiene %s %s actuales y %s %s reservadas.',
                    $fila->numero_folio,
                    $fila->item_codigo,
                    $fila->cantidad_actual,
                    $fila->unidad_medida,
                    $fila->cantidad_reservada,
                    $fila->unidad_medida,
                ),
                contexto: [
                    'item_codigo' => $fila->item_codigo,
                    'item_nombre' => $fila->item_nombre,
                    'cantidad_actual' => $fila->cantidad_actual,
                    'cantidad_reservada' => $fila->cantidad_reservada,
                    'unidad_medida' => $fila->unidad_medida,
                ],
            ));
    }
}
