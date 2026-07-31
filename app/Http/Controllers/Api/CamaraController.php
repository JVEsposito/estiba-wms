<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoCamara;
use App\Enums\EstadoPosicion;
use App\Http\Controllers\Controller;
use App\Http\Resources\CamaraPlanoResource;
use App\Http\Resources\CamaraResumenResource;
use App\Models\Camara;
use App\Models\PersonalAccessToken;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CamaraController extends Controller
{
    public function index(
        Request $request,
        AlcanceOperacionalUsuario $alcance,
    ): AnonymousResourceCollection {
        $contenidos = collect($alcance->contenidosVisibles($request->user()))
            ->map->value
            ->all();
        $camaras = Camara::query()
            ->where('estado', EstadoCamara::Activa->value)
            ->whereIn('contenido', $contenidos)
            ->withCount([
                'posiciones' => fn ($consulta) => $consulta
                    ->where('estado', EstadoPosicion::Activa->value),
            ])
            ->withCount([
                'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereHas('ubicacionesActuales'),
            ])
            ->withCount('ubicacionesSinPosicion')
            ->with($this->relacionesBloqueo())
            ->orderBy('codigo')
            ->get();

        return CamaraResumenResource::collection($camaras);
    }

    public function plano(
        Request $request,
        Camara $camara,
        AlcanceOperacionalUsuario $alcance,
    ): Response {
        abort_unless($camara->estado === EstadoCamara::Activa, 404);
        abort_unless($alcance->puedeVerCamara($request->user(), $camara), 403);

        $camara->load('bloqueo.sesionEstiba');
        $etag = $this->etagPlano($request, $camara);
        $respuestaCondicional = $this->configurarCachePlano(response('', 200), $etag);

        if ($respuestaCondicional->isNotModified($request)) {
            return $respuestaCondicional;
        }

        $camara->loadCount([
            'posiciones' => fn ($consulta) => $consulta
                ->where('estado', EstadoPosicion::Activa->value),
        ]);
        $camara->loadCount([
            'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                ->where('estado', EstadoPosicion::Activa->value)
                ->whereHas('ubicacionesActuales'),
        ]);
        $camara->loadCount('ubicacionesSinPosicion');
        $camara->loadMissing($this->relacionesBloqueo());
        $camara->load([
            'ubicacionesSinPosicion' => fn ($consulta) => $consulta
                ->with([
                    'folio.condicionSag',
                    'folio.material.item.cliente.temporada',
                    'folio.asignacionCargaActual.carga',
                ])
                ->orderBy('ubicado_at')
                ->orderBy('folio_id'),
            'posiciones' => fn ($consulta) => $consulta
                ->with([
                    'ubicacionesActuales.folio.condicionSag',
                    'ubicacionesActuales.folio.material.item.cliente.temporada',
                    'ubicacionesActuales.folio.asignacionCargaActual.carga',
                ])
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles)
                ->orderBy('banda')
                ->orderBy('nivel')
                ->orderBy('posicion'),
        ]);

        return $this->configurarCachePlano(
            (new CamaraPlanoResource($camara))->response(),
            $etag,
        );
    }

    private function etagPlano(Request $request, Camara $camara): string
    {
        $bloqueo = $camara->bloqueo;
        $sesion = $bloqueo?->sesionEstiba;
        $token = $request->user()?->currentAccessToken();
        $dispositivoId = $token instanceof PersonalAccessToken
            ? $token->dispositivo_id
            : null;
        $huella = json_encode([
            'camara_id' => $camara->id,
            'camara_actualizada_at' => $camara->updated_at?->toAtomString(),
            'version_plano' => $camara->version_plano,
            'usuario_id' => $request->user()?->getAuthIdentifier(),
            'dispositivo_id' => $dispositivoId,
            'bloqueo_id' => $bloqueo?->getKey(),
            'bloqueo_adquirido_at' => $bloqueo?->adquirido_at?->toAtomString(),
            'sesion_id' => $sesion?->id,
            'sesion_usuario_id' => $sesion?->user_id,
            'sesion_dispositivo_id' => $sesion?->dispositivo_id,
            'sesion_estado' => $sesion?->estado?->value,
            'sesion_ultima_actividad_at' => $sesion?->ultima_actividad_at?->toAtomString(),
        ], JSON_THROW_ON_ERROR);

        return 'plano-'.hash('sha256', $huella);
    }

    private function configurarCachePlano(Response $respuesta, string $etag): Response
    {
        $respuesta->setEtag($etag);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-cache');
        $respuesta->setVary('Authorization');
        $respuesta->headers->set('Access-Control-Expose-Headers', 'ETag');

        return $respuesta;
    }

    /**
     * @return array<int, string>
     */
    private function relacionesBloqueo(): array
    {
        return [
            'bloqueo.sesionEstiba.usuario:id,name',
            'bloqueo.sesionEstiba.dispositivo:id,nombre',
        ];
    }
}
