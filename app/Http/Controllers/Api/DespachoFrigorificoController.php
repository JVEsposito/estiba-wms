<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoIncidenciaCarga;
use App\Enums\TipoResolucionIncidenciaCarga;
use App\Http\Controllers\Controller;
use App\Http\Requests\CerrarDespachoFrigorificoRequest;
use App\Http\Requests\EnviarFolioAndenRequest;
use App\Http\Requests\ReportarIncidenciaCargaRequest;
use App\Http\Requests\ResolverIncidenciaCargaRequest;
use App\Http\Resources\CargaResource;
use App\Http\Resources\IncidenciaCargaFolioResource;
use App\Http\Resources\TareaCargaResource;
use App\Models\Anden;
use App\Models\Carga;
use App\Models\CargaFolio;
use App\Models\Folio;
use App\Models\IncidenciaCargaFolio;
use App\Models\SesionEstiba;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Cargas\ServicioDespachoFrigorifico;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DespachoFrigorificoController extends Controller
{
    public function tareas(Request $request, Carga $carga): AnonymousResourceCollection
    {
        Gate::authorize('consultar-cargas-operacion');

        return TareaCargaResource::collection(
            $carga->tareas()
                ->with(['camaraOrigen:id,codigo,nombre', 'responsable:id,name'])
                ->orderBy('created_at')
                ->get(),
        );
    }

    public function reportarIncidencia(
        ReportarIncidenciaCargaRequest $request,
        CargaFolio $cargaFolio,
        ContextoOperacional $contexto,
        ServicioDespachoFrigorifico $servicio,
    ): IncidenciaCargaFolioResource {
        $datos = $request->validated();
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $incidencia = $servicio->reportarIncidencia(
            operacionId: $datos['operacion_id'],
            asignacion: $cargaFolio,
            tipo: TipoIncidenciaCarga::from($datos['tipo']),
            descripcion: $datos['descripcion'] ?? null,
            sesion: SesionEstiba::query()->findOrFail($datos['sesion_estiba_id']),
            usuario: $usuario,
            dispositivo: $dispositivo,
        );

        return new IncidenciaCargaFolioResource($incidencia);
    }

    public function resolverIncidencia(
        ResolverIncidenciaCargaRequest $request,
        IncidenciaCargaFolio $incidencia,
        ServicioDespachoFrigorifico $servicio,
    ): IncidenciaCargaFolioResource {
        $datos = $request->validated();
        $resuelta = $servicio->resolverIncidencia(
            operacionId: $datos['operacion_id'],
            incidencia: $incidencia,
            resolucion: TipoResolucionIncidenciaCarga::from($datos['resolucion']),
            usuario: $request->user(),
            folioReemplazo: isset($datos['folio_reemplazo_id'])
                ? Folio::query()->findOrFail($datos['folio_reemplazo_id'])
                : null,
            observacion: $datos['observacion'] ?? null,
        );

        return new IncidenciaCargaFolioResource($resuelta);
    }

    public function enviarAnden(
        EnviarFolioAndenRequest $request,
        CargaFolio $cargaFolio,
        ContextoOperacional $contexto,
        ServicioDespachoFrigorifico $servicio,
    ): CargaResource {
        $datos = $request->validated();
        [$usuario, $dispositivo] = $contexto->obtener($request);
        $asignacion = $servicio->enviarFolioAnden(
            operacionId: $datos['operacion_id'],
            asignacion: $cargaFolio,
            anden: Anden::query()->findOrFail($datos['anden_id']),
            sesion: SesionEstiba::query()->findOrFail($datos['sesion_estiba_id']),
            usuario: $usuario,
            dispositivo: $dispositivo,
            versionCamaraConocida: $datos['version_camara_conocida'],
            generadoDispositivoAt: CarbonImmutable::parse($datos['generado_dispositivo_at']),
            advertenciasConfirmadas: $datos['advertencias_confirmadas'] ?? [],
        );

        return new CargaResource($this->cargarCarga($asignacion->carga));
    }

    public function cerrar(
        CerrarDespachoFrigorificoRequest $request,
        Carga $carga,
        ServicioDespachoFrigorifico $servicio,
    ): CargaResource {
        $datos = $request->validated();
        $cerrada = $servicio->cerrarDespacho(
            operacionId: $datos['operacion_id'],
            carga: $carga,
            usuario: $request->user(),
            patente: $datos['patente'],
            conductor: $datos['conductor'],
            observacion: $datos['observacion'] ?? null,
        );

        return new CargaResource($this->cargarCarga($cerrada));
    }

    private function cargarCarga(Carga $carga): Carga
    {
        return $carga->load([
            'camaraObjetivo:id,codigo,nombre',
            'andenPrevisto:id,codigo,nombre',
            'creadaPor:id,name',
            'actualizadaPor:id,name',
            'publicadaPor:id,name',
            'canceladaPor:id,name',
            'cerradaPor:id,name',
            'asignacionesActuales.asignadoPor:id,name',
            'asignacionesActuales.anden:id,codigo,nombre',
            'asignacionesActuales.folio.ubicacionActual.posicion.camara:id,codigo,nombre',
        ]);
    }
}
