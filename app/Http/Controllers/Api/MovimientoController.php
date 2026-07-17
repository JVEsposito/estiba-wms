<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoverFolioRequest;
use App\Http\Requests\MovimientosRecientesRequest;
use App\Http\Requests\UbicarFolioRequest;
use App\Http\Resources\MovimientoResource;
use App\Models\Folio;
use App\Models\Camara;
use App\Models\Movimiento;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Estiba\ServicioMovimientoEstiba;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MovimientoController extends Controller
{
    public function ubicar(
        UbicarFolioRequest $request,
        ContextoOperacional $contexto,
        ServicioMovimientoEstiba $servicio,
    ): Response {
        $datos = $request->validated();
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $movimiento = $servicio->ubicar(
            operacionId: $datos['operacion_id'],
            numeroFolio: $datos['numero_folio'],
            tipoBulto: TipoBulto::from($datos['tipo_bulto']),
            posicionDestino: Posicion::query()->findOrFail($datos['posicion_destino_id']),
            sesionDestino: SesionEstiba::query()->findOrFail($datos['sesion_destino_id']),
            usuario: $usuario,
            dispositivo: $dispositivo,
            versionDestinoConocida: $datos['version_destino_conocida'],
            generadoDispositivoAt: CarbonImmutable::parse(
                $datos['generado_dispositivo_at'],
            ),
            datosFolio: $datos['datos_folio'] ?? [],
            datosMaterial: $datos['datos_material'] ?? [],
            advertenciasConfirmadas: $datos['advertencias_confirmadas'] ?? [],
        );

        return (new MovimientoResource($this->cargarRelaciones($movimiento)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function mover(
        MoverFolioRequest $request,
        ContextoOperacional $contexto,
        ServicioMovimientoEstiba $servicio,
    ): Response {
        $datos = $request->validated();
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $movimiento = $servicio->mover(
            operacionId: $datos['operacion_id'],
            folio: Folio::query()->findOrFail($datos['folio_id']),
            posicionDestino: Posicion::query()->findOrFail($datos['posicion_destino_id']),
            sesionOrigen: SesionEstiba::query()->findOrFail($datos['sesion_origen_id']),
            sesionDestino: SesionEstiba::query()->findOrFail($datos['sesion_destino_id']),
            usuario: $usuario,
            dispositivo: $dispositivo,
            versionOrigenConocida: $datos['version_origen_conocida'],
            versionDestinoConocida: $datos['version_destino_conocida'],
            generadoDispositivoAt: CarbonImmutable::parse(
                $datos['generado_dispositivo_at'],
            ),
            advertenciasConfirmadas: $datos['advertencias_confirmadas'] ?? [],
        );

        return (new MovimientoResource($this->cargarRelaciones($movimiento)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function recientes(
        MovimientosRecientesRequest $request,
        AlcanceOperacionalUsuario $alcance,
    ): AnonymousResourceCollection {
        $datos = $request->validated();
        $contenidos = collect($alcance->contenidosVisibles($request->user()))
            ->map->value
            ->all();

        if ($camaraId = $datos['camara_id'] ?? null) {
            $camara = Camara::query()->findOrFail($camaraId);
            abort_unless($alcance->puedeVerCamara($request->user(), $camara), 403);
        }

        $movimientos = Movimiento::query()
            ->where(function ($consulta) use ($contenidos) {
                $consulta
                    ->whereHas('camaraOrigen', fn ($camara) => $camara
                        ->whereIn('contenido', $contenidos))
                    ->orWhereHas('camaraDestino', fn ($camara) => $camara
                        ->whereIn('contenido', $contenidos));
            })
            ->when($camaraId ?? null, function ($consulta, string $camaraId) {
                $consulta->where(function ($consulta) use ($camaraId) {
                    $consulta
                        ->where('camara_origen_id', $camaraId)
                        ->orWhere('camara_destino_id', $camaraId);
                });
            })
            ->with($this->relacionesMovimiento())
            ->latest('created_at')
            ->latest('id')
            ->limit($datos['limite'] ?? 3)
            ->get();

        return MovimientoResource::collection($movimientos);
    }

    private function cargarRelaciones(Movimiento $movimiento): Movimiento
    {
        return $movimiento->load($this->relacionesMovimiento());
    }

    /**
     * @return array<int, string>
     */
    private function relacionesMovimiento(): array
    {
        return [
            'folio',
            'usuario:id,name',
            'camaraOrigen:id,codigo,nombre',
            'posicionOrigen:id,banda,posicion,nivel,etiqueta',
            'camaraDestino:id,codigo,nombre',
            'posicionDestino:id,banda,posicion,nivel,etiqueta',
        ];
    }
}
