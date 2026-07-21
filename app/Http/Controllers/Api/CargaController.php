<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoCarga;
use App\Enums\EstadoIncidenciaCarga;
use App\Enums\EstadoOperacionalFolio;
use App\Enums\EstadoPosicion;
use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActualizarCargaRequest;
use App\Http\Requests\AgregarFoliosCargaRequest;
use App\Http\Requests\CrearCargaRequest;
use App\Http\Requests\VersionCargaRequest;
use App\Http\Resources\CargaResource;
use App\Http\Resources\FolioDisponibleCargaResource;
use App\Models\Carga;
use App\Models\Folio;
use App\Services\Cargas\ServicioCarga;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CargaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('consultar-catalogo-cargas');

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(EstadoCarga::class)],
            'solo_con_incidencias' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $busqueda = trim((string) ($filtros['q'] ?? ''));

        $cargas = Carga::query()
            ->with($this->relacionesDetalle())
            ->withCount([
                'incidencias as incidencias_abiertas' => fn (Builder $consulta): Builder => $consulta
                    ->where('incidencias_carga_folio.estado', EstadoIncidenciaCarga::Abierta->value),
            ])
            ->when(
                $busqueda !== '',
                fn (Builder $consulta): Builder => $consulta->where(
                    fn (Builder $coincidencia): Builder => $coincidencia
                        ->where('codigo', 'like', "%{$busqueda}%")
                        ->orWhere('numero_orden_externa', 'like', "%{$busqueda}%")
                        ->orWhere('observacion', 'like', "%{$busqueda}%")
                        ->orWhereHas(
                            'camaraObjetivo',
                            fn (Builder $camara): Builder => $camara
                                ->where('codigo', 'like', "%{$busqueda}%")
                                ->orWhere('nombre', 'like', "%{$busqueda}%"),
                        ),
                ),
            )
            ->when(
                isset($filtros['estado']),
                fn (Builder $consulta): Builder => $consulta
                    ->where('estado', $filtros['estado']),
            )
            ->when(
                $request->boolean('solo_con_incidencias'),
                fn (Builder $consulta): Builder => $consulta->whereHas(
                    'incidencias',
                    fn (Builder $incidencia): Builder => $incidencia
                        ->where('incidencias_carga_folio.estado', EstadoIncidenciaCarga::Abierta->value),
                ),
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($filtros['per_page'] ?? 25))
            ->withQueryString();

        return CargaResource::collection($cargas);
    }

    public function pendientes(): AnonymousResourceCollection
    {
        Gate::authorize('consultar-cargas-operacion');

        $cargas = Carga::query()
            ->whereIn(
                'estado',
                collect(EstadoCarga::visiblesEnOperacion())
                    ->map(fn (EstadoCarga $estado): string => $estado->value)
                    ->all(),
            )
            ->with($this->relacionesDetalle())
            ->withCount([
                'incidencias as incidencias_abiertas' => fn (Builder $consulta): Builder => $consulta
                    ->where('incidencias_carga_folio.estado', EstadoIncidenciaCarga::Abierta->value),
            ])
            ->orderByRaw(
                "CASE prioridad WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 ELSE 3 END",
            )
            ->orderBy('publicada_at')
            ->get();

        return CargaResource::collection($cargas);
    }

    public function foliosDisponibles(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('gestionar-cargas');

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'equivalente_a' => ['nullable', 'uuid', Rule::exists('folios', 'id')],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $busqueda = trim((string) ($filtros['q'] ?? ''));
        $folioOriginal = isset($filtros['equivalente_a'])
            ? Folio::query()->findOrFail($filtros['equivalente_a'])
            : null;

        $folios = Folio::query()
            ->where('activo', true)
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta
                ->where('activa', true))
            ->where('estado_operacional', EstadoOperacionalFolio::Disponible->value)
            ->whereIn('tipo_bulto', [
                TipoBulto::Pallet->value,
                TipoBulto::Saldo->value,
            ])
            ->whereDoesntHave('asignacionCargaActual')
            ->when($folioOriginal, function (Builder $consulta, Folio $original): Builder {
                foreach ([
                    'tipo_bulto',
                    'condicion_sag_id',
                    'variedad',
                    'calibre',
                    'marca',
                    'exportadora',
                ] as $campo) {
                    $valor = $original->{$campo};
                    $consulta->where($campo, $valor instanceof \BackedEnum ? $valor->value : $valor);
                }

                return $consulta->where('id', '!=', $original->id);
            })
            ->whereHas(
                'ubicacionActual.posicion',
                fn (Builder $posicion): Builder => $posicion
                    ->where('estado', EstadoPosicion::Activa->value)
                    ->whereHas(
                        'camara',
                        fn (Builder $camara): Builder => $camara
                            ->where('estado', EstadoCamara::Activa->value)
                            ->where('contenido', ContenidoCamara::Productos->value),
                    ),
            )
            ->when(
                $busqueda !== '',
                fn (Builder $consulta): Builder => $consulta->where(
                    fn (Builder $coincidencia): Builder => $coincidencia
                        ->where('numero_folio', 'like', "%{$busqueda}%")
                        ->orWhere('tipo_bulto', 'like', "%{$busqueda}%")
                        ->orWhere('variedad', 'like', "%{$busqueda}%")
                        ->orWhere('calibre', 'like', "%{$busqueda}%")
                        ->orWhere('marca', 'like', "%{$busqueda}%")
                        ->orWhere('exportadora', 'like', "%{$busqueda}%")
                        ->orWhereHas(
                            'condicionSag',
                            fn (Builder $condicion): Builder => $condicion
                                ->where('codigo', 'like', "%{$busqueda}%")
                                ->orWhere('nombre', 'like', "%{$busqueda}%"),
                        )
                        ->orWhereHas(
                            'ubicacionActual.posicion',
                            fn (Builder $posicion): Builder => $posicion
                                ->where('etiqueta', 'like', "%{$busqueda}%")
                                ->orWhereHas(
                                    'camara',
                                    fn (Builder $camara): Builder => $camara
                                        ->where('codigo', 'like', "%{$busqueda}%")
                                        ->orWhere('nombre', 'like', "%{$busqueda}%"),
                                ),
                        ),
                ),
            )
            ->with([
                'condicionSag:id,codigo,nombre',
                'ubicacionActual.posicion.camara:id,codigo,nombre',
            ])
            ->orderBy('numero_folio')
            ->paginate((int) ($filtros['per_page'] ?? 25))
            ->withQueryString();

        return FolioDisponibleCargaResource::collection($folios);
    }

    public function store(
        CrearCargaRequest $request,
        ServicioCarga $servicio,
    ): JsonResponse {
        $carga = $servicio->crear(
            $request->validated(),
            $request->user(),
        );

        return (new CargaResource($this->cargarDetalle($carga)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Carga $carga): CargaResource
    {
        Gate::authorize('consultar-catalogo-cargas');

        return new CargaResource($this->cargarDetalle($carga));
    }

    public function update(
        ActualizarCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        $actualizada = $servicio->actualizar(
            $carga,
            $request->validated(),
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function agregarFolios(
        AgregarFoliosCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        $actualizada = $servicio->agregarFolios(
            $carga,
            $request->validated('folios'),
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function quitarFolio(
        VersionCargaRequest $request,
        Carga $carga,
        Folio $folio,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $actualizada = $servicio->quitarFolio(
            $carga,
            $folio,
            $request->user(),
            $request->integer('version_esperada'),
            $request->validated('motivo'),
        );

        return new CargaResource($this->cargarDetalle($actualizada));
    }

    public function publicar(
        VersionCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $publicada = $servicio->publicar(
            $carga,
            $request->user(),
            $request->integer('version_esperada'),
        );

        return new CargaResource($this->cargarDetalle($publicada));
    }

    public function cancelar(
        VersionCargaRequest $request,
        Carga $carga,
        ServicioCarga $servicio,
    ): CargaResource {
        Gate::authorize('gestionar-cargas');

        $cancelada = $servicio->cancelar(
            $carga,
            $request->user(),
            $request->integer('version_esperada'),
            $request->validated('motivo'),
        );

        return new CargaResource($this->cargarDetalle($cancelada));
    }

    private function cargarDetalle(Carga $carga): Carga
    {
        return $carga
            ->load($this->relacionesDetalle())
            ->loadCount([
                'incidencias as incidencias_abiertas' => fn (Builder $consulta): Builder => $consulta
                    ->where('incidencias_carga_folio.estado', EstadoIncidenciaCarga::Abierta->value),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function relacionesDetalle(): array
    {
        return [
            'temporada:id,codigo,nombre,activa',
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
            'asignacionesHistoricas.anden:id,codigo,nombre',
            'asignacionesHistoricas.folio.ubicacionActual.posicion.camara:id,codigo,nombre',
            'tareas.camaraOrigen:id,codigo,nombre',
            'tareas.responsable:id,name',
        ];
    }
}
