<?php

namespace App\Http\Controllers\Api;

use App\Enums\MotivoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogoValidacionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $temporada = DB::table('temporadas')
            ->where('activa', true)
            ->orderByDesc('fecha_inicio')
            ->first();
        abort_unless($temporada, 404, 'No existe una temporada activa para validación.');

        $articulos = DB::table('articulos_validacion')
            ->where('temporada_id', $temporada->id)
            ->where('activo', true)
            ->orderBy('especie')
            ->orderBy('variedad')
            ->orderBy('calibre')
            ->orderBy('envase')
            ->get();
        $categorias = DB::table('categorias_validacion')
            ->where('temporada_id', $temporada->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
        $origenes = DB::table('origenes_validacion')
            ->where('temporada_id', $temporada->id)
            ->where('activo', true)
            ->orderBy('cliente')
            ->orderBy('marca')
            ->orderBy('csg')
            ->get();
        $combinaciones = DB::table('combinaciones_validacion as combinacion')
            ->join('articulos_validacion as articulo', 'articulo.id', '=', 'combinacion.articulo_validacion_id')
            ->join('origenes_validacion as origen', 'origen.id', '=', 'combinacion.origen_validacion_id')
            ->where('combinacion.temporada_id', $temporada->id)
            ->where('combinacion.activo', true)
            ->where('articulo.activo', true)
            ->where('origen.activo', true)
            ->orderBy('articulo.especie')
            ->orderBy('origen.cliente')
            ->get([
                'combinacion.id',
                'combinacion.articulo_validacion_id',
                'combinacion.origen_validacion_id',
                'combinacion.codigo_externo',
            ]);

        return response()->json([
            'temporada' => $temporada,
            'categorias' => $categorias,
            'articulos' => $articulos,
            'origenes' => $origenes,
            'combinaciones' => $combinaciones,
            'tipos_bulto' => [TipoBulto::Pallet->value, TipoBulto::Saldo->value],
            'resultados' => array_column(ResultadoValidacionPallet::cases(), 'value'),
            'motivos' => array_column(MotivoValidacionPallet::cases(), 'value'),
            'generado_at' => now()->toAtomString(),
        ]);
    }
}
