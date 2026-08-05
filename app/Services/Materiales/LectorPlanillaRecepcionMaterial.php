<?php

namespace App\Services\Materiales;

use App\Services\Validacion\LectorPlanillaValidacion;
use Illuminate\Support\Str;

class LectorPlanillaRecepcionMaterial extends LectorPlanillaValidacion
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
            'codigo', 'codigo_item', 'item', 'item_codigo', 'sku' => 'codigo_item',
            'cantidad_documental', 'cantidad_guia', 'documental', 'segun_guia' => 'cantidad_documental',
            'cantidad_contada', 'cantidad_fisica', 'contada', 'fisica' => 'cantidad_contada',
            'cantidad_aceptada', 'cantidad_recibida', 'aceptada', 'recibida' => 'cantidad_aceptada',
            'cantidad_rechazada', 'rechazada', 'merma' => 'cantidad_rechazada',
            'unidades_por_bulto', 'cantidad_por_bulto', 'tamano_bulto', 'contenido_bulto' => 'unidades_por_bulto',
            'lote', 'lote_proveedor', 'numero_lote' => 'lote_proveedor',
            'fecha_fabricacion', 'fabricacion', 'fecha_elaboracion' => 'fecha_fabricacion',
            'fecha_vencimiento', 'vencimiento', 'fecha_caducidad' => 'fecha_vencimiento',
            'bloqueado', 'bloqueo', 'estado_bloqueo' => 'bloqueado',
            'motivo_bloqueo', 'razon_bloqueo' => 'motivo_bloqueo',
            'observacion', 'observaciones', 'comentario' => 'observacion',
            default => $normalizada,
        };
    }
}
