<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoCamara;
use App\Enums\EstadoPosicion;
use App\Http\Controllers\Controller;
use App\Http\Resources\CamaraPlanoResource;
use App\Http\Resources\CamaraResumenResource;
use App\Models\Camara;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CamaraController extends Controller
{
    public function index(
        Request $request,
        AlcanceOperacionalUsuario $alcance,
    ): AnonymousResourceCollection
    {
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
                    ->whereHas('ubicacionActual'),
            ])
            ->with($this->relacionesBloqueo())
            ->orderBy('codigo')
            ->get();

        return CamaraResumenResource::collection($camaras);
    }

    public function plano(
        Request $request,
        Camara $camara,
        AlcanceOperacionalUsuario $alcance,
    ): CamaraPlanoResource
    {
        abort_unless($camara->estado === EstadoCamara::Activa, 404);
        abort_unless($alcance->puedeVerCamara($request->user(), $camara), 403);

        $camara->loadCount([
            'posiciones' => fn ($consulta) => $consulta
                ->where('estado', EstadoPosicion::Activa->value),
        ]);
        $camara->loadCount([
            'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                ->where('estado', EstadoPosicion::Activa->value)
                ->whereHas('ubicacionActual'),
        ]);
        $camara->load([
            ...$this->relacionesBloqueo(),
            'posiciones' => fn ($consulta) => $consulta
                ->with([
                    'ubicacionActual.folio.condicionSag',
                    'ubicacionActual.folio.material.item',
                    'ubicacionActual.folio.asignacionCargaActual.carga',
                ])
                ->where('banda', '<=', $camara->cantidad_bandas)
                ->where('posicion', '<=', $camara->posiciones_por_banda)
                ->where('nivel', '<=', $camara->cantidad_niveles)
                ->orderBy('banda')
                ->orderBy('nivel')
                ->orderBy('posicion'),
        ]);

        return new CamaraPlanoResource($camara);
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
