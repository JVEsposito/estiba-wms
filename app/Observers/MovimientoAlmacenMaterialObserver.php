<?php

namespace App\Observers;

use App\Enums\TipoMovimientoAlmacenMaterial;
use App\Enums\TipoMovimientoInventarioMaterial;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\MovimientoInventarioMaterial;

class MovimientoAlmacenMaterialObserver
{
    public function created(MovimientoAlmacenMaterial $movimiento): void
    {
        if ($movimiento->tipo === TipoMovimientoAlmacenMaterial::Entrega) {
            return;
        }

        $tipo = match ($movimiento->tipo) {
            TipoMovimientoAlmacenMaterial::Consumo => TipoMovimientoInventarioMaterial::ConsumoCentroCosto,
            TipoMovimientoAlmacenMaterial::Ajuste => TipoMovimientoInventarioMaterial::AjusteAlmacen,
            TipoMovimientoAlmacenMaterial::Devolucion => TipoMovimientoInventarioMaterial::Devolucion,
            TipoMovimientoAlmacenMaterial::Transferencia => TipoMovimientoInventarioMaterial::TransferenciaInterna,
            TipoMovimientoAlmacenMaterial::Entrega => TipoMovimientoInventarioMaterial::TransferenciaInterna,
        };
        $totalAnterior = data_get(
            $movimiento->metadatos,
            'total_empresa_anterior',
            data_get($movimiento->metadatos, 'total_empresa', 0),
        );
        $totalResultante = data_get(
            $movimiento->metadatos,
            'total_empresa_resultante',
            data_get($movimiento->metadatos, 'total_empresa', $totalAnterior),
        );
        $cantidad = match ($movimiento->tipo) {
            TipoMovimientoAlmacenMaterial::Consumo => -abs((float) $movimiento->cantidad),
            TipoMovimientoAlmacenMaterial::Ajuste => (float) $movimiento->cantidad,
            default => 0,
        };

        $movimiento->loadMissing(['almacenOrigen', 'almacenDestino']);

        MovimientoInventarioMaterial::create([
            'folio_id' => $movimiento->folio_id,
            'item_material_id' => $movimiento->item_material_id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'cantidad_anterior' => $totalAnterior,
            'cantidad_resultante' => $totalResultante,
            'despacho_material_id' => $movimiento->despacho_material_id,
            'retiro_material_id' => $movimiento->retiro_material_id,
            'user_id' => $movimiento->user_id,
            'dispositivo_id' => $movimiento->dispositivo_id,
            'destino_nombre' => $movimiento->almacenDestino?->nombre
                ?? $movimiento->almacenOrigen?->nombre,
            'destino_centro_costo' => $movimiento->centro_costo,
            'motivo' => $movimiento->motivo,
            'metadatos' => [
                'movimiento_almacen_material_id' => $movimiento->id,
                'almacen_origen_id' => $movimiento->almacen_origen_id,
                'almacen_destino_id' => $movimiento->almacen_destino_id,
                'cantidad_operacional' => $movimiento->cantidad,
                'documento_relacionado' => $movimiento->documento_relacionado,
            ],
            'ocurrido_at' => $movimiento->ocurrido_at,
        ]);
    }
}
