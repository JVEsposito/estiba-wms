<?php

namespace App\Services\Materiales;

use App\Enums\CategoriaOperacionalMaterial;
use App\Enums\EstadoVersionRecetaMaterial;
use App\Models\Cliente;
use App\Models\DetalleVersionRecetaMaterial;
use App\Models\ItemMaterial;
use App\Models\RecetaMaterial;
use App\Models\User;
use App\Models\VersionRecetaMaterial;
use App\Services\Temporadas\ServicioTemporadaActiva;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServicioVersionRecetaMaterial
{
    public function __construct(
        private readonly ServicioTemporadaActiva $temporadaActiva,
        private readonly ServicioTransformacionMaterial $transformacion,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(
        RecetaMaterial $receta,
        array $datos,
        User $usuario,
    ): RecetaMaterial {
        return DB::transaction(function () use ($receta, $datos, $usuario): RecetaMaterial {
            $temporada = $this->temporadaActiva->obtener(bloquear: true);
            $receta = RecetaMaterial::query()
                ->with(['cliente', 'itemSalida'])
                ->lockForUpdate()
                ->findOrFail($receta->id);

            if (! $receta->activa || $receta->temporada_id !== $temporada->id) {
                throw new DomainException(
                    'Solo pueden versionarse recetas activas de la temporada global vigente.',
                );
            }

            $cliente = Cliente::query()
                ->whereKey($receta->cliente_id)
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $componentes = collect($datos['componentes']);

            if ($componentes->where('es_componente_principal', true)->count() !== 1) {
                throw new DomainException('La receta debe tener exactamente un componente principal.');
            }

            $versiones = VersionRecetaMaterial::query()
                ->where('receta_material_id', $receta->id)
                ->lockForUpdate()
                ->get();
            $siguienteNumero = ((int) $versiones->max('numero_version')) + 1;
            $ahora = now();

            foreach ($versiones->where('estado', EstadoVersionRecetaMaterial::Activa) as $versionActiva) {
                $versionActiva->update([
                    'estado' => EstadoVersionRecetaMaterial::Retirada,
                    'retirado_at' => $ahora,
                ]);
            }

            $version = VersionRecetaMaterial::create([
                'receta_material_id' => $receta->id,
                'numero_version' => $siguienteNumero,
                'estado' => EstadoVersionRecetaMaterial::Activa,
                'cantidad_base_salida' => $this->cantidad($datos['cantidad_base_salida']),
                'unidad_medida_salida' => $receta->itemSalida->unidad_medida,
                'creado_por_user_id' => $usuario->id,
                'activado_at' => $ahora,
            ]);
            $detallesSnapshot = [];

            foreach ($componentes as $componente) {
                $item = $this->validarItemEntrada(
                    $componente['item_entrada_id'],
                    $cliente,
                    $temporada->id,
                );
                $detalle = DetalleVersionRecetaMaterial::create([
                    'version_receta_material_id' => $version->id,
                    'item_entrada_id' => $item->id,
                    'cantidad_estandar' => $this->cantidad($componente['cantidad_estandar']),
                    'unidad_medida' => $item->unidad_medida,
                    'es_componente_principal' => (bool) $componente['es_componente_principal'],
                    'factor_conversion' => $this->cantidad($componente['factor_conversion'] ?? 1),
                    'merma_estandar_porcentaje' => $this->porcentaje(
                        $componente['merma_estandar_porcentaje'] ?? 0,
                    ),
                    'tolerancia_porcentaje' => $this->porcentaje(
                        $componente['tolerancia_porcentaje'] ?? 0,
                    ),
                ]);
                $detallesSnapshot[] = [
                    'id' => $detalle->id,
                    'item_id' => $item->id,
                    'codigo' => $item->codigo,
                    'nombre' => $item->nombre,
                    'categoria_operacional' => $item->categoria_operacional->value,
                    'cantidad_estandar' => $detalle->cantidad_estandar,
                    'unidad_medida' => $detalle->unidad_medida,
                    'es_componente_principal' => $detalle->es_componente_principal,
                    'factor_conversion' => $detalle->factor_conversion,
                    'merma_estandar_porcentaje' => $detalle->merma_estandar_porcentaje,
                    'tolerancia_porcentaje' => $detalle->tolerancia_porcentaje,
                ];
            }

            $version->update(['snapshot' => [
                'receta' => [
                    'id' => $receta->id,
                    'nombre' => $receta->nombre,
                ],
                'cliente' => [
                    'id' => $cliente->id,
                    'codigo' => $cliente->codigo,
                    'nombre' => $cliente->nombre,
                ],
                'salida' => [
                    'item_id' => $receta->itemSalida->id,
                    'codigo' => $receta->itemSalida->codigo,
                    'nombre' => $receta->itemSalida->nombre,
                    'cantidad_base' => $version->cantidad_base_salida,
                    'unidad_medida' => $receta->itemSalida->unidad_medida,
                ],
                'componentes' => $detallesSnapshot,
            ]]);
            $receta->update(['actualizado_por_user_id' => $usuario->id]);

            return $this->transformacion->cargarReceta($receta->refresh());
        }, attempts: 3);
    }

    private function validarItemEntrada(
        string $itemId,
        Cliente $cliente,
        string $temporadaId,
    ): ItemMaterial {
        $item = ItemMaterial::query()
            ->with(['cliente.cliente', 'cliente.temporada'])
            ->whereKey($itemId)
            ->where('activo', true)
            ->lockForUpdate()
            ->first();

        if (! $item
            || ! $item->cliente?->activo
            || ! $item->cliente?->cliente?->activo
            || $item->cliente->cliente_id !== $cliente->id
            || $item->cliente->temporada?->temporada_id !== $temporadaId
            || ! $item->cliente->temporada?->activa
            || ! in_array($item->categoria_operacional, [
                CategoriaOperacionalMaterial::Insumo,
                CategoriaOperacionalMaterial::MaterialMp,
            ], true)) {
            throw new DomainException(
                'Uno de los componentes no pertenece al cliente y temporada activos o posee una categoría inválida.',
            );
        }

        return $item;
    }

    private function cantidad(mixed $valor): float
    {
        $cantidad = round((float) $valor, 3);

        if ($cantidad <= 0) {
            throw new DomainException('Las cantidades deben ser mayores que cero.');
        }

        return $cantidad;
    }

    private function porcentaje(mixed $valor): float
    {
        $porcentaje = round((float) $valor, 4);

        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new DomainException('Los porcentajes deben encontrarse entre 0 y 100.');
        }

        return $porcentaje;
    }
}
