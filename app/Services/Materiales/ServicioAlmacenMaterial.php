<?php

namespace App\Services\Materiales;

use App\Enums\TipoAlmacenMaterial;
use App\Models\AlmacenMaterial;
use App\Models\DestinoMaterial;
use App\Models\FolioMaterial;
use App\Models\SaldoMaterialAlmacen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServicioAlmacenMaterial
{
    public function bodegaCentral(?User $actor = null, bool $bloquear = false): AlmacenMaterial
    {
        $consulta = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $existente = $consulta->first();

        if ($existente) {
            return $existente;
        }

        $actor ??= User::query()->where('activo', true)->orderBy('id')->first();

        if (! $actor) {
            throw new LogicException(
                'No existe un usuario activo para crear la Bodega Central de Materiales.',
            );
        }

        return AlmacenMaterial::query()->firstOrCreate(
            ['codigo' => AlmacenMaterial::CODIGO_BODEGA_CENTRAL],
            [
                'nombre' => 'Bodega Central de Materiales',
                'tipo' => TipoAlmacenMaterial::Fisica,
                'centro_costo' => 'BODEGA',
                'requiere_ubicacion_fisica' => true,
                'descripcion' => 'Almacén físico principal del inventario de materiales.',
                'origen_sistema' => 'estiba_wms',
                'activo' => true,
                'creado_por_user_id' => $actor->id,
                'actualizado_por_user_id' => $actor->id,
            ],
        );
    }

    public function almacenDesdeDestino(DestinoMaterial $destino, User $actor): AlmacenMaterial
    {
        $almacen = AlmacenMaterial::query()->lockForUpdate()->findOrFail($destino->id);
        $cambios = [];

        if (! $almacen->codigo) {
            $cambios['codigo'] = 'ALM-'.strtoupper(substr(str_replace('-', '', $almacen->id), 0, 8));
        }

        if (! $almacen->tipo) {
            $cambios['tipo'] = TipoAlmacenMaterial::Virtual;
        }

        if ($almacen->requiere_ubicacion_fisica) {
            $cambios['requiere_ubicacion_fisica'] = false;
        }

        if ($cambios !== []) {
            $almacen->update([
                ...$cambios,
                'actualizado_por_user_id' => $actor->id,
            ]);
        }

        return $almacen->refresh();
    }

    public function saldo(
        FolioMaterial $folio,
        AlmacenMaterial $almacen,
        bool $bloquear = true,
    ): SaldoMaterialAlmacen {
        $consulta = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->folio_id)
            ->where('almacen_material_id', $almacen->id);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->firstOrCreate([
            'folio_id' => $folio->folio_id,
            'almacen_material_id' => $almacen->id,
        ], [
            'cantidad_actual' => 0,
            'cantidad_reservada' => 0,
        ]);
    }

    public function sincronizarFolio(FolioMaterial $folio, ?User $actor = null): void
    {
        DB::transaction(function () use ($folio, $actor): void {
            $folio = FolioMaterial::query()
                ->with('folio.ubicacionActual')
                ->lockForUpdate()
                ->findOrFail($folio->folio_id);
            $bodega = $this->bodegaCentral($actor, bloquear: true);
            $saldos = SaldoMaterialAlmacen::query()
                ->where('folio_id', $folio->folio_id)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            if ($saldos->isEmpty()) {
                $ubicacion = $folio->folio?->ubicacionActual;
                SaldoMaterialAlmacen::create([
                    'folio_id' => $folio->folio_id,
                    'almacen_material_id' => $bodega->id,
                    'cantidad_actual' => $folio->cantidad_actual,
                    'cantidad_reservada' => $folio->cantidad_reservada,
                    'camara_id' => $ubicacion?->camara_id,
                    'posicion_id' => $ubicacion?->posicion_id,
                ]);

                return;
            }

            $saldoBodega = $saldos->firstWhere('almacen_material_id', $bodega->id)
                ?? $this->saldo($folio, $bodega);
            $totalDistribuido = round((float) $saldos->sum('cantidad_actual'), 3);
            $diferencia = round((float) $folio->cantidad_actual - $totalDistribuido, 3);

            if ($diferencia > 0.0001) {
                $saldoBodega->increment('cantidad_actual', $diferencia);
            } elseif ($diferencia < -0.0001) {
                $pendiente = abs($diferencia);
                $ordenados = $saldos
                    ->sortByDesc(fn (SaldoMaterialAlmacen $saldo): int => $saldo->almacen_material_id === $bodega->id ? 1 : 0)
                    ->values();

                foreach ($ordenados as $saldo) {
                    if ($pendiente <= 0.0001) {
                        break;
                    }

                    $descontar = min($pendiente, (float) $saldo->cantidad_actual);
                    $saldo->update([
                        'cantidad_actual' => round((float) $saldo->cantidad_actual - $descontar, 3),
                    ]);
                    $pendiente = round($pendiente - $descontar, 3);
                }
            }

            $saldoBodega->refresh()->update([
                'cantidad_reservada' => min(
                    (float) $saldoBodega->cantidad_actual,
                    (float) $folio->cantidad_reservada,
                ),
            ]);

            $this->sincronizarUbicacion($folio);
        }, attempts: 3);
    }

    public function sincronizarUbicacion(FolioMaterial $folio): void
    {
        $folio->loadMissing('folio.ubicacionActual');
        $bodega = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->first();

        if (! $bodega) {
            return;
        }

        $saldo = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->folio_id)
            ->where('almacen_material_id', $bodega->id)
            ->first();

        if (! $saldo) {
            return;
        }

        $ubicacion = $folio->folio?->ubicacionActual;
        $saldo->update([
            'camara_id' => (float) $saldo->cantidad_actual > 0 ? $ubicacion?->camara_id : null,
            'posicion_id' => (float) $saldo->cantidad_actual > 0 ? $ubicacion?->posicion_id : null,
        ]);
    }
}
