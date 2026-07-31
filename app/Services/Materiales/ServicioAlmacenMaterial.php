<?php

namespace App\Services\Materiales;

use App\Enums\EstadoOperacionalFolio;
use App\Enums\TipoAlmacenMaterial;
use App\Models\AlmacenMaterial;
use App\Models\DestinoMaterial;
use App\Models\FolioMaterial;
use App\Models\SaldoMaterialAlmacen;
use App\Models\UbicacionActual;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        DB::table('destinos_materiales')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'codigo' => AlmacenMaterial::CODIGO_BODEGA_CENTRAL,
            'nombre' => 'Bodega Central de Materiales',
            'tipo' => TipoAlmacenMaterial::Fisica->value,
            'centro_costo' => 'BODEGA',
            'requiere_ubicacion_fisica' => true,
            'descripcion' => 'Almacén físico principal del inventario de materiales.',
            'origen_sistema' => 'estiba_wms',
            'activo' => true,
            'creado_por_user_id' => $actor->id,
            'actualizado_por_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $consulta = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->firstOrFail();
    }

    public function almacenDesdeDestino(DestinoMaterial $destino, User $actor): AlmacenMaterial
    {
        $almacen = AlmacenMaterial::query()->lockForUpdate()->findOrFail($destino->id);
        $cambios = [];

        if (! $almacen->codigo) {
            $cambios['codigo'] = 'ALM-'.Str::upper(
                substr(str_replace('-', '', $almacen->id), 0, 8),
            );
        }

        if (! $almacen->tipo) {
            $cambios['tipo'] = TipoAlmacenMaterial::Virtual;
        }

        if (($cambios['tipo'] ?? $almacen->tipo) === TipoAlmacenMaterial::Virtual
            && $almacen->requiere_ubicacion_fisica) {
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

    public function asegurarSaldo(FolioMaterial $folio, AlmacenMaterial $almacen): void
    {
        DB::table('saldos_materiales_almacenes')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'folio_id' => $folio->folio_id,
            'almacen_material_id' => $almacen->id,
            'cantidad_actual' => 0,
            'cantidad_reservada' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function saldo(
        FolioMaterial $folio,
        AlmacenMaterial $almacen,
        bool $bloquear = true,
    ): SaldoMaterialAlmacen {
        $this->asegurarSaldo($folio, $almacen);
        $consulta = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->folio_id)
            ->where('almacen_material_id', $almacen->id);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->firstOrFail();
    }

    /**
     * @param  array<int, string>  $almacenIds
     * @return Collection<int, SaldoMaterialAlmacen>
     */
    public function saldosBloqueados(
        FolioMaterial $folio,
        array $almacenIds,
    ): Collection {
        $ids = collect($almacenIds)->filter()->unique()->sort()->values();

        return SaldoMaterialAlmacen::query()
            ->where('folio_id', $folio->folio_id)
            ->whereIn('almacen_material_id', $ids)
            ->orderBy('almacen_material_id')
            ->orderBy('folio_id')
            ->lockForUpdate()
            ->get();
    }

    public function inicializarFolio(FolioMaterial $folio): void
    {
        DB::transaction(function () use ($folio): void {
            $folio = FolioMaterial::query()
                ->with('folio.ubicacionActual')
                ->lockForUpdate()
                ->findOrFail($folio->folio_id);
            $bodega = $this->bodegaCentral(bloquear: true);
            $existente = SaldoMaterialAlmacen::query()
                ->where('folio_id', $folio->folio_id)
                ->where('almacen_material_id', $bodega->id)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return;
            }

            $ubicacion = $folio->folio?->ubicacionActual;
            SaldoMaterialAlmacen::create([
                'folio_id' => $folio->folio_id,
                'almacen_material_id' => $bodega->id,
                'cantidad_actual' => $folio->cantidad_actual,
                'cantidad_reservada' => $folio->cantidad_reservada,
                'camara_id' => (float) $folio->cantidad_actual > 0
                    ? $ubicacion?->camara_id
                    : null,
                'posicion_id' => (float) $folio->cantidad_actual > 0
                    ? $ubicacion?->posicion_id
                    : null,
                'version' => 0,
            ]);
        }, attempts: 3);
    }

    public function aplicarCambioLegado(FolioMaterial $folio): void
    {
        $deltaActual = round(
            (float) $folio->cantidad_actual - (float) $folio->getOriginal('cantidad_actual'),
            3,
        );
        $deltaReservada = round(
            (float) $folio->cantidad_reservada - (float) $folio->getOriginal('cantidad_reservada'),
            3,
        );

        if (abs($deltaActual) <= 0.0001 && abs($deltaReservada) <= 0.0001) {
            return;
        }

        DB::transaction(function () use ($folio, $deltaActual, $deltaReservada): void {
            $bloqueado = FolioMaterial::query()
                ->with('folio')
                ->lockForUpdate()
                ->findOrFail($folio->folio_id);
            $bodega = $this->bodegaCentral(bloquear: true);
            $saldo = $this->saldo($bloqueado, $bodega);
            $cantidadActual = round((float) $saldo->cantidad_actual + $deltaActual, 3);
            $cantidadReservada = round(
                (float) $saldo->cantidad_reservada + $deltaReservada,
                3,
            );

            $this->validarCantidades($cantidadActual, $cantidadReservada);
            $saldo->update([
                'cantidad_actual' => $cantidadActual,
                'cantidad_reservada' => $cantidadReservada,
                'camara_id' => $cantidadActual > 0 ? $saldo->camara_id : null,
                'posicion_id' => $cantidadActual > 0 ? $saldo->posicion_id : null,
                'version' => DB::raw('version + 1'),
            ]);

            if ($cantidadActual <= 0.0001) {
                UbicacionActual::withoutEvents(fn () => UbicacionActual::query()
                    ->where('folio_id', $folio->folio_id)
                    ->delete());
            }

            $this->actualizarEstadoGlobal($bloqueado, (float) $folio->cantidad_actual);
            $this->validarProyeccion($bloqueado->folio_id);
        }, attempts: 3);
    }

    public function sincronizarProyeccion(FolioMaterial|string $folio): FolioMaterial
    {
        $folioId = $folio instanceof FolioMaterial ? $folio->folio_id : $folio;

        return DB::transaction(function () use ($folioId): FolioMaterial {
            $material = FolioMaterial::query()
                ->with('folio')
                ->lockForUpdate()
                ->findOrFail($folioId);
            $totales = SaldoMaterialAlmacen::query()
                ->where('folio_id', $folioId)
                ->selectRaw(
                    'COALESCE(SUM(cantidad_actual), 0) AS cantidad_actual, '
                    .'COALESCE(SUM(cantidad_reservada), 0) AS cantidad_reservada',
                )
                ->firstOrFail();
            $cantidadActual = round((float) $totales->cantidad_actual, 3);
            $cantidadReservada = round((float) $totales->cantidad_reservada, 3);

            $this->validarCantidades($cantidadActual, $cantidadReservada);
            $material->forceFill([
                'cantidad_actual' => $cantidadActual,
                'cantidad_reservada' => $cantidadReservada,
            ])->saveQuietly();
            $this->sincronizarUbicacionCompatibilidad($material);
            $this->actualizarEstadoGlobal($material, $cantidadActual);
            $this->validarProyeccion($folioId);

            return $material->refresh();
        }, attempts: 3);
    }

    public function sincronizarUbicacionDesdeCompatibilidad(FolioMaterial $folio): void
    {
        DB::transaction(function () use ($folio): void {
            $folio = FolioMaterial::query()
                ->with('folio.ubicacionActual')
                ->lockForUpdate()
                ->findOrFail($folio->folio_id);
            $bodega = $this->bodegaCentral(bloquear: true);
            $saldo = $this->saldo($folio, $bodega);
            $ubicacion = $folio->folio?->ubicacionActual;

            $saldo->update([
                'camara_id' => (float) $saldo->cantidad_actual > 0
                    ? $ubicacion?->camara_id
                    : null,
                'posicion_id' => (float) $saldo->cantidad_actual > 0
                    ? $ubicacion?->posicion_id
                    : null,
                'version' => DB::raw('version + 1'),
            ]);
        }, attempts: 3);
    }

    public function validarProyeccion(string $folioId): void
    {
        $material = FolioMaterial::query()->findOrFail($folioId);
        $totales = SaldoMaterialAlmacen::query()
            ->where('folio_id', $folioId)
            ->selectRaw(
                'COALESCE(SUM(cantidad_actual), 0) AS cantidad_actual, '
                .'COALESCE(SUM(cantidad_reservada), 0) AS cantidad_reservada',
            )
            ->firstOrFail();

        if (abs((float) $material->cantidad_actual - (float) $totales->cantidad_actual) > 0.0001
            || abs((float) $material->cantidad_reservada - (float) $totales->cantidad_reservada) > 0.0001) {
            throw new LogicException(
                'La proyección global del folio no coincide con sus saldos por almacén.',
            );
        }
    }

    private function sincronizarUbicacionCompatibilidad(FolioMaterial $folio): void
    {
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

        if (! $saldo || (float) $saldo->cantidad_actual <= 0 || ! $saldo->camara_id) {
            UbicacionActual::withoutEvents(fn () => UbicacionActual::query()
                ->where('folio_id', $folio->folio_id)
                ->delete());

            return;
        }

        UbicacionActual::withoutEvents(fn () => UbicacionActual::query()->updateOrCreate(
            ['folio_id' => $folio->folio_id],
            [
                'camara_id' => $saldo->camara_id,
                'posicion_id' => $saldo->posicion_id,
                'movimiento_id' => null,
                'ubicado_at' => now(),
            ],
        ));
    }

    private function actualizarEstadoGlobal(FolioMaterial $folio, float $totalEmpresa): void
    {
        $folio->loadMissing('folio');

        if (! $folio->folio) {
            return;
        }

        if ($totalEmpresa <= 0.0001) {
            $folio->folio->update([
                'estado_operacional' => EstadoOperacionalFolio::Agotado,
                'activo' => false,
            ]);

            return;
        }

        $bodega = AlmacenMaterial::query()
            ->where('codigo', AlmacenMaterial::CODIGO_BODEGA_CENTRAL)
            ->first();
        $saldoBodega = $bodega
            ? SaldoMaterialAlmacen::query()
                ->where('folio_id', $folio->folio_id)
                ->where('almacen_material_id', $bodega->id)
                ->first()
            : null;
        $estado = $folio->motivo_bloqueo
            ? EstadoOperacionalFolio::Bloqueado
            : ((float) ($saldoBodega?->cantidad_actual ?? 0) > 0
                && ! $saldoBodega?->camara_id
                    ? EstadoOperacionalFolio::PendienteUbicacion
                    : EstadoOperacionalFolio::Disponible);

        if (! $folio->folio->activo || $folio->folio->estado_operacional !== $estado) {
            $folio->folio->update([
                'estado_operacional' => $estado,
                'activo' => true,
            ]);
        }
    }

    private function validarCantidades(float $actual, float $reservada): void
    {
        if ($actual < -0.0001) {
            throw new DomainException('El saldo de almacén no puede ser negativo.');
        }

        if ($reservada < -0.0001 || $reservada > $actual + 0.0001) {
            throw new DomainException(
                'La cantidad reservada debe estar entre cero y la cantidad actual.',
            );
        }
    }
}
