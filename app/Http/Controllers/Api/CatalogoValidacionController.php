<?php

namespace App\Http\Controllers\Api;

use App\Enums\MotivoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use App\Services\Temporadas\ServicioTemporadaActiva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CatalogoValidacionController extends Controller
{
    public function __invoke(
        Request $request,
        ServicioTemporadaActiva $temporadas,
    ): Response {
        $temporada = $temporadas->buscar();
        abort_unless($temporada, 404, 'No existe una temporada activa para validación.');

        $etag = sprintf(
            'validacion-catalogo-%s-%d',
            $temporada->id,
            $temporada->version_catalogo,
        );
        $respuestaCondicional = $this->configurarCache(response('', 200), $etag);

        if ($respuestaCondicional->isNotModified($request)) {
            return $respuestaCondicional;
        }

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

        return $this->configurarCache(
            response()->json([
                'temporada' => $temporada,
                'categorias' => $categorias,
                'articulos' => $articulos,
                'origenes' => $origenes,
                'combinaciones' => $combinaciones,
                'tipos_bulto' => [TipoBulto::Pallet->value, TipoBulto::Saldo->value],
                'resultados' => array_column(ResultadoValidacionPallet::cases(), 'value'),
                'motivos' => array_column(MotivoValidacionPallet::cases(), 'value'),
                'generado_at' => now()->toAtomString(),
            ]),
            $etag,
        );
    }

    private function configurarCache(Response $respuesta, string $etag): Response
    {
        $respuesta->setEtag($etag);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-cache');
        $respuesta->setVary('Authorization');
        $respuesta->headers->set('Access-Control-Expose-Headers', 'ETag');

        return $respuesta;
    }
}
