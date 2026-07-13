<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CamaraPlanoResource;
use App\Http\Resources\CamaraResumenResource;
use App\Models\Camara;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CamaraController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $camaras = Camara::query()
            ->withCount('posiciones')
            ->withCount([
                'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                    ->whereHas('ubicacionActual'),
            ])
            ->with($this->relacionesBloqueo())
            ->orderBy('codigo')
            ->get();

        return CamaraResumenResource::collection($camaras);
    }

    public function plano(Camara $camara): CamaraPlanoResource
    {
        $camara->loadCount('posiciones');
        $camara->loadCount([
            'posiciones as posiciones_ocupadas_count' => fn ($consulta) => $consulta
                ->whereHas('ubicacionActual'),
        ]);
        $camara->load([
            ...$this->relacionesBloqueo(),
            'posiciones' => fn ($consulta) => $consulta
                ->with('ubicacionActual.folio.condicionSag')
                ->orderBy('fila')
                ->orderBy('nivel')
                ->orderBy('profundidad'),
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
