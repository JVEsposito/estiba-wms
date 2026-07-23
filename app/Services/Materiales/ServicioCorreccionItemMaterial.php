<?php

namespace App\Services\Materiales;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoReservaMaterial;
use App\Enums\TipoMovimientoInventarioMaterial;
use App\Models\CorreccionItemFolioMaterial;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\MovimientoInventarioMaterial;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioCorreccionItemMaterial
{
    public function corregir(
        FolioMaterial $folioMaterial,
        string $operacionId,
        string $itemNuevoId,
        string $motivo,
        User $usuario,
    ): CorreccionItemFolioMaterial {
        return DB::transaction(function () use (
            $folioMaterial,
            $operacionId,
            $itemNuevoId,
            $motivo,
            $usuario,
        ): CorreccionItemFolioMaterial {
            $existente = CorreccionItemFolioMaterial::query()
                ->where('operacion_id', $operacionId)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->folio_id !== $folioMaterial->folio_id
                    || $existente->item_nuevo_id !== $itemNuevoId
                    || $existente->motivo !== $motivo) {
                    throw new DomainException('La operación de corrección ya fue utilizada con otros datos.');
                }

                return $this->cargar($existente);
            }

            $material = FolioMaterial::query()
                ->with(['folio.ubicacionActual.posicion.camara', 'item.cliente.temporada'])
                ->lockForUpdate()
                ->findOrFail($folioMaterial->folio_id);
            $nuevo = ItemMaterial::query()
                ->with('cliente.temporada')
                ->whereKey($itemNuevoId)
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if (! $nuevo || ! $nuevo->cliente?->activo || ! $nuevo->cliente?->temporada?->activa) {
                throw new DomainException('El nuevo ítem no se encuentra activo en la temporada global.');
            }

            if (! $material->folio?->activo
                || $material->folio->ubicacionActual?->posicion?->camara?->contenido !== ContenidoCamara::Materiales) {
                throw new DomainException('Solo se puede corregir un ítem actualmente estibado en materiales.');
            }

            if ($material->item_material_id === $nuevo->id) {
                throw new DomainException('Selecciona un ítem diferente al registrado actualmente.');
            }

            if ($material->item->cliente_material_id !== $nuevo->cliente_material_id) {
                throw new DomainException('La corrección no puede cambiar el cliente propietario del material.');
            }

            if ($material->unidad_medida !== $nuevo->unidad_medida) {
                throw new DomainException('El nuevo ítem debe utilizar la misma unidad de medida.');
            }

            if ($material->reservas()
                ->where('estado', EstadoReservaMaterial::Activa->value)
                ->lockForUpdate()
                ->exists()) {
                throw new DomainException('El ítem posee reservas activas y no puede corregirse.');
            }

            if ($material->retiros()->lockForUpdate()->exists()) {
                throw new DomainException('El ítem ya posee retiros y no puede corregirse.');
            }

            $anterior = $material->item;
            $cantidad = (float) $material->cantidad_actual;
            $ahora = now();
            $correccion = CorreccionItemFolioMaterial::create([
                'operacion_id' => $operacionId,
                'folio_id' => $material->folio_id,
                'item_anterior_id' => $anterior->id,
                'item_nuevo_id' => $nuevo->id,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
                'user_id' => $usuario->id,
                'ocurrido_at' => $ahora,
            ]);
            $metadatos = [
                'correccion_id' => $correccion->id,
                'operacion_id' => $operacionId,
                'item_anterior' => ['id' => $anterior->id, 'codigo' => $anterior->codigo],
                'item_nuevo' => ['id' => $nuevo->id, 'codigo' => $nuevo->codigo],
            ];

            MovimientoInventarioMaterial::create([
                'folio_id' => $material->folio_id,
                'item_material_id' => $anterior->id,
                'tipo' => TipoMovimientoInventarioMaterial::CorreccionItemSalida,
                'cantidad' => -$cantidad,
                'cantidad_anterior' => $cantidad,
                'cantidad_resultante' => 0,
                'user_id' => $usuario->id,
                'motivo' => $motivo,
                'metadatos' => $metadatos,
                'ocurrido_at' => $ahora,
            ]);

            $material->update(['item_material_id' => $nuevo->id]);

            MovimientoInventarioMaterial::create([
                'folio_id' => $material->folio_id,
                'item_material_id' => $nuevo->id,
                'tipo' => TipoMovimientoInventarioMaterial::CorreccionItemEntrada,
                'cantidad' => $cantidad,
                'cantidad_anterior' => 0,
                'cantidad_resultante' => $cantidad,
                'user_id' => $usuario->id,
                'motivo' => $motivo,
                'metadatos' => $metadatos,
                'ocurrido_at' => $ahora,
            ]);

            return $this->cargar($correccion);
        });
    }

    public function cargar(CorreccionItemFolioMaterial $correccion): CorreccionItemFolioMaterial
    {
        return $correccion->load([
            'folioMaterial.folio:id,numero_folio',
            'itemAnterior.cliente',
            'itemNuevo.cliente',
            'usuario:id,name',
        ]);
    }
}
