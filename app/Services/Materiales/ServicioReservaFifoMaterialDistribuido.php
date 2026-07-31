<?php

namespace App\Services\Materiales;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Models\AlmacenMaterial;
use App\Models\FolioMaterial;
use App\Models\SaldoMaterialAlmacen;
use Closure;
use LogicException;

class ServicioReservaFifoMaterialDistribuido extends ServicioReservaFifoMaterial
{
    /**
     * @param  Closure(FolioMaterial, float, int): void  $registrarReserva
     */
    public function reservar(
        string $itemMaterialId,
        float $cantidadRequerida,
        Closure $registrarReserva,
    ): float {
        $pendiente = round($cantidadRequerida, 3);
        $ordenFifo = 1;

        while ($pendiente > 0.0001) {
            $saldo = $this->siguienteDisponibleBloqueado($itemMaterialId);

            if (! $saldo) {
                break;
            }

            $disponible = round(
                (float) $saldo->cantidad_actual - (float) $saldo->cantidad_reservada,
                3,
            );

            if ($disponible <= 0) {
                throw new LogicException(
                    'El selector FIFO bloqueó un saldo de almacén sin disponibilidad.',
                );
            }

            $folio = $saldo->folioMaterial;
            $cantidad = min($pendiente, $disponible);
            $registrarReserva($folio, $cantidad, $ordenFifo++);
            $saldo->increment('cantidad_reservada', $cantidad);
            $folio->increment('cantidad_reservada', $cantidad);
            $pendiente = round($pendiente - $cantidad, 3);
        }

        return max(0, $pendiente);
    }

    private function siguienteDisponibleBloqueado(
        string $itemMaterialId,
    ): ?SaldoMaterialAlmacen {
        return SaldoMaterialAlmacen::query()
            ->with('folioMaterial')
            ->join('destinos_materiales as am', 'am.id', '=', 'saldos_materiales_almacenes.almacen_material_id')
            ->join('folios_materiales as fm', 'fm.folio_id', '=', 'saldos_materiales_almacenes.folio_id')
            ->join('folios as f', 'f.id', '=', 'fm.folio_id')
            ->leftJoin('camaras as ca', 'ca.id', '=', 'saldos_materiales_almacenes.camara_id')
            ->leftJoin('posiciones as p', 'p.id', '=', 'saldos_materiales_almacenes.posicion_id')
            ->select('saldos_materiales_almacenes.*')
            ->where('am.codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->where('am.activo', true)
            ->where('fm.item_material_id', $itemMaterialId)
            ->whereColumn(
                'saldos_materiales_almacenes.cantidad_actual',
                '>',
                'saldos_materiales_almacenes.cantidad_reservada',
            )
            ->whereNull('fm.motivo_bloqueo')
            ->where('f.activo', true)
            ->where('f.estado_operacional', EstadoOperacionalFolio::Disponible->value)
            ->where('ca.contenido', ContenidoCamara::Materiales->value)
            ->where('ca.estado', EstadoCamara::Activa->value)
            ->where(function ($ubicacion): void {
                $ubicacion
                    ->whereNull('saldos_materiales_almacenes.posicion_id')
                    ->orWhere('p.estado', EstadoPosicion::Activa->value);
            })
            ->orderBy('f.fecha_ingreso')
            ->orderBy('f.numero_folio')
            ->orderBy('f.id')
            ->lockForUpdate()
            ->first();
    }
}
