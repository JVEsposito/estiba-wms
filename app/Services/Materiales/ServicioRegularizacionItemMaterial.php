<?php

namespace App\Services\Materiales;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\EstadoDespachoMaterial;
use App\Enums\EstadoOrdenTransformacionMaterial;
use App\Enums\EstadoRecepcionMaterial;
use App\Enums\EstadoVersionRecetaMaterial;
use App\Models\DetalleDespachoMaterial;
use App\Models\DetalleRecepcionMaterial;
use App\Models\FolioMaterial;
use App\Models\ItemMaterial;
use App\Models\OrdenTransformacionMaterial;
use App\Models\RecetaMaterial;
use App\Models\RegularizacionItemMaterial;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioRegularizacionItemMaterial
{
    public function __construct(
        private readonly ServicioVersionRecetaMaterial $versiones,
    ) {}

    public function regularizar(
        ItemMaterial $duplicado,
        string $canonicoId,
        string $operacionId,
        string $motivo,
        User $usuario,
    ): RegularizacionItemMaterial {
        return DB::transaction(function () use (
            $duplicado,
            $canonicoId,
            $operacionId,
            $motivo,
            $usuario,
        ): RegularizacionItemMaterial {
            $existente = RegularizacionItemMaterial::query()
                ->where('operacion_id', $operacionId)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->item_duplicado_id !== $duplicado->id
                    || $existente->item_canonico_id !== $canonicoId
                    || $existente->motivo !== $motivo) {
                    throw new DomainException('La operación de regularización ya fue utilizada con otros datos.');
                }

                return $this->cargar($existente);
            }

            $duplicado = ItemMaterial::query()
                ->with('cliente.temporada')
                ->lockForUpdate()
                ->findOrFail($duplicado->id);
            $canonico = ItemMaterial::query()
                ->with('cliente.temporada')
                ->lockForUpdate()
                ->findOrFail($canonicoId);

            $this->validarItems($duplicado, $canonico);
            $this->validarDependenciasOperativas($duplicado);

            $recetas = RecetaMaterial::query()
                ->where('item_salida_id', $duplicado->id)
                ->where('activa', true)
                ->with(['versiones' => fn ($consulta) => $consulta
                    ->where('estado', EstadoVersionRecetaMaterial::Activa->value)
                    ->with('detalles')])
                ->lockForUpdate()
                ->get();
            $versionesNuevas = [];

            foreach ($recetas as $receta) {
                $version = $receta->versiones->first();
                if (! $version) {
                    throw new DomainException('Una receta activa del ítem duplicado no posee una versión activa.');
                }

                $componentes = $version->detalles->map(fn ($detalle): array => [
                    'item_entrada_id' => $detalle->item_entrada_id,
                    'cantidad_estandar' => $detalle->cantidad_estandar,
                    'es_componente_principal' => $detalle->es_componente_principal,
                    'factor_conversion' => $detalle->factor_conversion,
                    'merma_estandar_porcentaje' => $detalle->merma_estandar_porcentaje,
                    'tolerancia_porcentaje' => $detalle->tolerancia_porcentaje,
                ])->all();
                $principal = collect($componentes)->firstWhere('es_componente_principal', true);

                if (($principal['item_entrada_id'] ?? null) !== $canonico->id) {
                    throw new DomainException(
                        "La receta {$receta->nombre} no usa el Material MP canónico como componente principal.",
                    );
                }

                $receta->update([
                    'item_salida_id' => $canonico->id,
                    'actualizado_por_user_id' => $usuario->id,
                ]);
                $actualizada = $this->versiones->crear($receta, [
                    'cantidad_base_salida' => $version->cantidad_base_salida,
                    'unidades_por_folio_salida' => $version->unidades_por_folio_salida,
                    'componentes' => $componentes,
                ], $usuario);
                $versionesNuevas[] = [
                    'receta_id' => $receta->id,
                    'receta' => $receta->nombre,
                    'version_anterior' => $version->numero_version,
                    'version_nueva' => $actualizada->versiones->max('numero_version'),
                ];
            }

            $snapshotDuplicado = $this->snapshotItem($duplicado);
            $duplicado->update([
                'activo' => false,
                'actualizado_por_user_id' => $usuario->id,
            ]);

            $regularizacion = RegularizacionItemMaterial::create([
                'operacion_id' => $operacionId,
                'item_duplicado_id' => $duplicado->id,
                'item_canonico_id' => $canonico->id,
                'snapshot' => [
                    'duplicado' => $snapshotDuplicado,
                    'canonico' => $this->snapshotItem($canonico),
                    'recetas_versionadas' => $versionesNuevas,
                ],
                'motivo' => $motivo,
                'user_id' => $usuario->id,
                'ocurrido_at' => now(),
            ]);

            return $this->cargar($regularizacion);
        }, attempts: 3);
    }

    private function validarItems(ItemMaterial $duplicado, ItemMaterial $canonico): void
    {
        if (! $duplicado->activo || ! $canonico->activo) {
            throw new DomainException('Ambos ítems deben encontrarse activos antes de regularizarlos.');
        }
        if ($duplicado->id === $canonico->id) {
            throw new DomainException('El ítem canónico debe ser diferente del duplicado.');
        }
        if ($duplicado->cliente_material_id !== $canonico->cliente_material_id) {
            throw new DomainException('Los ítems deben pertenecer al mismo cliente y temporada.');
        }
        if ($duplicado->unidad_medida !== $canonico->unidad_medida) {
            throw new DomainException('Los ítems deben utilizar la misma unidad de medida.');
        }
        if ($duplicado->categoria_operacional !== CategoriaOperacionalMaterial::MaterialPt
            || $canonico->categoria_operacional !== CategoriaOperacionalMaterial::MaterialMp) {
            throw new DomainException('Solo se puede consolidar un Material PT duplicado sobre su Material MP canónico.');
        }
        if (! $canonico->cliente?->activo || ! $canonico->cliente?->temporada?->activa) {
            throw new DomainException('El Material MP canónico debe pertenecer a la temporada activa.');
        }
    }

    private function validarDependenciasOperativas(ItemMaterial $duplicado): void
    {
        if (FolioMaterial::query()
            ->where('item_material_id', $duplicado->id)
            ->where(fn ($consulta) => $consulta
                ->where('cantidad_actual', '>', 0)
                ->orWhere('cantidad_reservada', '>', 0))
            ->lockForUpdate()
            ->exists()) {
            throw new DomainException('El Material PT duplicado aún posee stock o reservas. Consúmelo o corrígelo antes de consolidar.');
        }

        if (DetalleRecepcionMaterial::query()
            ->where('item_material_id', $duplicado->id)
            ->whereHas('recepcion', fn ($consulta) => $consulta
                ->where('estado', EstadoRecepcionMaterial::Borrador->value))
            ->lockForUpdate()
            ->exists()) {
            throw new DomainException('El Material PT duplicado está incluido en una recepción en borrador.');
        }

        if (DetalleDespachoMaterial::query()
            ->where('item_material_id', $duplicado->id)
            ->whereHas('despacho', fn ($consulta) => $consulta
                ->whereIn('estado', [
                    EstadoDespachoMaterial::Pendiente->value,
                    EstadoDespachoMaterial::Parcial->value,
                ]))
            ->lockForUpdate()
            ->exists()) {
            throw new DomainException('El Material PT duplicado está incluido en un despacho abierto.');
        }

        if (OrdenTransformacionMaterial::query()
            ->whereIn('estado', [
                EstadoOrdenTransformacionMaterial::Borrador->value,
                EstadoOrdenTransformacionMaterial::Planificada->value,
                EstadoOrdenTransformacionMaterial::EnProceso->value,
                EstadoOrdenTransformacionMaterial::PendienteCierre->value,
            ])
            ->whereHas('versionReceta.receta', fn ($consulta) => $consulta
                ->where('item_salida_id', $duplicado->id))
            ->lockForUpdate()
            ->exists()) {
            throw new DomainException('El Material PT duplicado posee una orden de transformación abierta.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshotItem(ItemMaterial $item): array
    {
        return [
            'id' => $item->id,
            'cliente_material_id' => $item->cliente_material_id,
            'codigo' => $item->codigo,
            'nombre' => $item->nombre,
            'categoria' => $item->categoria,
            'categoria_operacional' => $item->categoria_operacional?->value,
            'unidad_medida' => $item->unidad_medida,
            'activo' => $item->activo,
        ];
    }

    private function cargar(RegularizacionItemMaterial $regularizacion): RegularizacionItemMaterial
    {
        return $regularizacion->load(['itemDuplicado', 'itemCanonico', 'usuario:id,name']);
    }
}
