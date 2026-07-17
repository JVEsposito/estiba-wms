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
        $temporada = DB::table('temporadas')->where('activa', true)->orderByDesc('fecha_inicio')->first();
        abort_unless($temporada, 404, 'No existe una temporada activa para validación.');

        return response()->json([
            'temporada' => $temporada,
            'articulos' => DB::table('articulos_validacion')
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->orderBy('especie')->orderBy('variedad')->orderBy('calibre')->get(),
            'origenes' => DB::table('origenes_validacion')
                ->where('temporada_id', $temporada->id)
                ->where('activo', true)
                ->orderBy('cliente')->orderBy('marca')->orderBy('csg')->get(),
            'tipos_bulto' => [TipoBulto::Pallet->value, TipoBulto::Saldo->value],
            'resultados' => array_column(ResultadoValidacionPallet::cases(), 'value'),
            'motivos' => array_column(MotivoValidacionPallet::cases(), 'value'),
            'generado_at' => now()->toAtomString(),
        ]);
    }
}
