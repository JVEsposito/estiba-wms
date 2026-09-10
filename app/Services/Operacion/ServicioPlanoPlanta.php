<?php

namespace App\Services\Operacion;

use App\Enums\TipoAlmacenMaterial;
use App\Exceptions\ConflictoOperacion;
use App\Models\AlmacenMaterial;
use App\Models\Anden;
use App\Models\Camara;
use App\Models\PlanoPlanta;
use App\Models\TunelPrefrio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioPlanoPlanta
{
    public const CODIGO_PRINCIPAL = 'principal';

    /**
     * @param  array<int, array<string, mixed>>  $camaras
     * @param  array<int, array<string, mixed>>  $tuneles
     * @return array<string, mixed>
     */
    public function obtener(array $camaras, array $tuneles, bool $puedeEditar): array
    {
        $plano = PlanoPlanta::query()->where('codigo', self::CODIGO_PRINCIPAL)->first();
        $catalogo = $this->catalogo($camaras, $tuneles);

        return [
            'configurado' => $plano !== null,
            'nombre' => $plano?->nombre ?? 'Planta principal',
            'version' => $plano?->version ?? 0,
            'actualizado_at' => $plano?->updated_at?->toAtomString(),
            'puede_editar' => $puedeEditar,
            'elementos' => $plano?->elementos ?? [],
            'catalogo' => $catalogo,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(array $datos, User $usuario): PlanoPlanta
    {
        $this->validarElementos($datos['elementos']);

        return DB::transaction(function () use ($datos, $usuario): PlanoPlanta {
            $plano = PlanoPlanta::query()
                ->where('codigo', self::CODIGO_PRINCIPAL)
                ->lockForUpdate()
                ->first();
            $versionActual = $plano?->version ?? 0;

            if ($versionActual !== (int) $datos['version_esperada']) {
                throw new ConflictoOperacion('El plano fue actualizado por otra sesión. Recarga antes de volver a guardar.');
            }

            $atributos = [
                'nombre' => trim($datos['nombre']),
                'version' => $versionActual + 1,
                'elementos' => array_values($datos['elementos']),
                'actualizado_por_user_id' => $usuario->id,
            ];

            if ($plano) {
                $plano->update($atributos);
                return $plano->refresh();
            }

            return PlanoPlanta::create([
                'codigo' => self::CODIGO_PRINCIPAL,
                ...$atributos,
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $elementos
     */
    private function validarElementos(array $elementos): void
    {
        $referencias = collect($elementos)
            ->reject(fn (array $elemento): bool => $elemento['tipo'] === 'zona')
            ->groupBy('tipo');
        $errores = [];

        foreach ($referencias as $tipo => $items) {
            if ($items->pluck('referencia_id')->contains(null)) {
                $errores["elementos.$tipo"][] = 'Los recintos existentes requieren una referencia válida.';
                continue;
            }

            if ($items->pluck('referencia_id')->duplicates()->isNotEmpty()) {
                $errores["elementos.$tipo"][] = 'Un recinto no puede aparecer más de una vez en el plano.';
            }

            $modelo = match ($tipo) {
                'camara' => Camara::query(),
                'tunel' => TunelPrefrio::query(),
                'anden' => Anden::query()->where('activo', true),
                'almacen' => AlmacenMaterial::query()
                    ->where('activo', true)
                    ->where('tipo', TipoAlmacenMaterial::Fisica->value),
            };
            $ids = $items->pluck('referencia_id')->unique()->values();
            if ($modelo->whereKey($ids)->count() !== $ids->count()) {
                $errores["elementos.$tipo"][] = 'Uno de los recintos referenciados ya no existe o no está activo.';
            }
        }

        foreach ($elementos as $indice => $elemento) {
            if ($elemento['x'] + $elemento['ancho'] > 10000 || $elemento['y'] + $elemento['alto'] > 10000) {
                $errores["elementos.$indice"][] = 'El elemento debe quedar completamente dentro de los límites del plano.';
            }
            if ($elemento['tipo'] === 'zona' && ! in_array($elemento['categoria'] ?? 'otro', ['packing', 'bodega', 'pasillo', 'patio', 'oficina', 'muelle', 'otro'], true)) {
                $errores["elementos.$indice.categoria"][] = 'La categoría de la zona no es válida.';
            }
            if ($elemento['tipo'] === 'zona' && $elemento['referencia_id'] !== null) {
                $errores["elementos.$indice.referencia_id"][] = 'Las áreas dibujadas no deben referenciar un recinto del catálogo.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $camaras
     * @param  array<int, array<string, mixed>>  $tuneles
     * @return array<int, array<string, mixed>>
     */
    private function catalogo(array $camaras, array $tuneles): array
    {
        $items = collect($camaras)->map(fn (array $camara): array => [
            'tipo' => 'camara',
            'id' => $camara['id'],
            'codigo' => $camara['codigo'],
            'nombre' => $camara['nombre'],
            'detalle' => ($camara['ocupacion_porcentaje'] ?? 0).'% ocupado',
            'tono' => match ($camara['nivel_ocupacion'] ?? null) {
                'critica' => 'critical',
                'advertencia' => 'warning',
                default => 'success',
            },
        ]);

        $items = $items->concat(collect($tuneles)->map(fn (array $tunel): array => [
            'tipo' => 'tunel',
            'id' => $tunel['id'],
            'codigo' => $tunel['codigo'],
            'nombre' => $tunel['nombre'],
            'detalle' => str_replace('_', ' ', $tunel['estado_operacional']),
            'tono' => $tunel['proceso_activo'] ? 'info' : 'neutral',
        ]));

        $andenes = Anden::query()
            ->where('activo', true)
            ->with(['presenciaActiva.carga:id,codigo'])
            ->orderBy('codigo')
            ->get()
            ->map(function (Anden $anden): array {
                $presencia = $anden->presenciaActiva;
                return [
                    'tipo' => 'anden',
                    'id' => $anden->id,
                    'codigo' => $anden->codigo,
                    'nombre' => $anden->nombre,
                    'detalle' => $presencia ? 'Ocupado · '.($presencia->patente ?: $presencia->carga?->codigo) : 'Disponible',
                    'tono' => $presencia ? 'warning' : 'success',
                ];
            });

        $almacenes = AlmacenMaterial::query()
            ->where('activo', true)
            ->where('tipo', TipoAlmacenMaterial::Fisica->value)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (AlmacenMaterial $almacen): array => [
                'tipo' => 'almacen',
                'id' => $almacen->id,
                'codigo' => $almacen->codigo,
                'nombre' => $almacen->nombre,
                'detalle' => 'Bodega física',
                'tono' => 'neutral',
            ]);

        return $items->concat($andenes)->concat($almacenes)->values()->all();
    }
}
