<?php

namespace App\Services\Materiales;

use App\Services\Validacion\LectorPlanillaValidacion;
use Illuminate\Support\Str;

class LectorPlanillaMaterial extends LectorPlanillaValidacion
{
    protected function normalizarCabecera(string $cabecera): string
    {
        $normalizada = Str::of($cabecera)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ($normalizada) {
            'temporada', 'temporada_codigo', 'codigo_temporada' => 'temporada_codigo',
            'cliente', 'cliente_codigo', 'codigo_cliente' => 'cliente_codigo',
            'codigo', 'codigo_item', 'item', 'sku' => 'codigo',
            'nombre', 'descripcion', 'descripcion_item', 'formato' => 'nombre',
            'categoria', 'familia', 'grupo' => 'categoria',
            'tipo_item', 'tipo_material', 'categoria_operacional', 'clasificacion_operacional' => 'categoria_operacional',
            'unidad_medida', 'unidad', 'um', 'uom' => 'unidad_medida',
            'codigo_externo', 'codigo_erp', 'id_externo' => 'codigo_externo',
            'activo', 'estado' => 'activo',
            default => $normalizada,
        };
    }
}
