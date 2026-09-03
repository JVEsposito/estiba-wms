<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoBulto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarFolioUbicacionRequest;
use App\Http\Requests\MoverFolioRequest;
use App\Http\Requests\MovimientosRecientesRequest;
use App\Http\Requests\UbicarFolioRequest;
use App\Http\Resources\MovimientoResource;
use App\Models\Camara;
use App\Models\Folio;
use App\Models\Movimiento;
use App\Models\Posicion;
use App\Models\SesionEstiba;
use App\Models\TareaMovimiento;
use App\Models\Temporada;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use App\Services\Estiba\ServicioMovimientoEstiba;
use App\Services\Estiba\ServicioRecomendacionUbicacion;
use App\Services\Folios\ServicioHabilitacionAlmacenamiento;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MovimientoController extends Controller
{
    public function consultarFolio(
        ConsultarFolioUbicacionRequest $request,
        ServicioHabilitacionAlmacenamiento $habilitacion,
        ServicioRecomendacionUbicacion $recomendaciones,
    ): JsonResponse {
        $numeroFolio = mb_strtoupper(trim((string) $request->validated('numero_folio')));
        $folio = Folio::query()
            ->where('numero_folio', $numeroFolio)
            ->with([
                'condicionSag:id,codigo,nombre',
                'ubicacionActual.camara:id,codigo,nombre,contenido,estado',
                'ubicacionActual.posicion:id,camara_id,etiqueta,estado',
                'material.item:id,codigo,nombre,unidad_medida',
            ])
            ->first();

        if (! $folio) {
            return response()->json([
                'data' => [
                    'existe' => false,
                    'numero_folio' => $numeroFolio,
                ],
            ]);
        }

        [$disponible, $mensaje] = $this->disponibilidadUbicacionInicial(
            $folio,
            $habilitacion,
        );
        $ubicacion = $folio->ubicacionActual;
        $camaraUbicacion = $ubicacion?->camara ?? $ubicacion?->posicion?->camara;
        $material = $folio->material;
        $recomendacion = $disponible
            ? $recomendaciones->recomendar(
                $folio,
                $request->user(),
                $request->validated('camara_id'),
            )
            : null;

        return response()->json([
            'data' => [
                'existe' => true,
                'id' => $folio->id,
                'numero_folio' => $folio->numero_folio,
                'tipo_bulto' => $folio->tipo_bulto->value,
                'estado_operacional' => $folio->estado_operacional->value,
                'condicion_termica' => $folio->condicion_termica?->value,
                'habilitacion_almacenamiento' => $folio->habilitacion_almacenamiento?->value,
                'disponible_ubicacion' => $disponible,
                'mensaje_disponibilidad' => $mensaje,
                'origen_sistema' => $folio->origen_sistema,
                'condicion_sag' => $folio->condicionSag ? [
                    'id' => $folio->condicionSag->id,
                    'codigo' => $folio->condicionSag->codigo,
                    'nombre' => $folio->condicionSag->nombre,
                ] : null,
                'variedad' => $folio->variedad,
                'calibre' => $folio->calibre,
                'marca' => $folio->marca,
                'exportadora' => $folio->exportadora,
                'recomendacion_ubicacion' => $recomendacion,
                'ubicacion_actual' => $ubicacion && $camaraUbicacion ? [
                    'camara' => [
                        'id' => $camaraUbicacion->id,
                        'codigo' => $camaraUbicacion->codigo,
                        'nombre' => $camaraUbicacion->nombre,
                    ],
                    'posicion' => $ubicacion->posicion ? [
                        'id' => $ubicacion->posicion->id,
                        'etiqueta' => $ubicacion->posicion->etiqueta,
                    ] : null,
                ] : null,
                'material' => $material ? [
                    'item_material_id' => $material->item_material_id,
                    'item' => [
                        'codigo' => $material->item->codigo,
                        'nombre' => $material->item->nombre,
                    ],
                    'cantidad' => $material->cantidad_inicial,
                    'lote' => $material->lote,
                    'proveedor' => $material->proveedor,
                    'observacion' => $material->observacion,
                ] : null,
            ],
        ]);
    }

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
            camaraDestino: Camara::query()->findOrFail($datos['camara_destino_id']),
            posicionDestino: isset($datos['posicion_destino_id'])
                ? Posicion::query()->findOrFail($datos['posicion_destino_id'])
                : null,
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
            tareaMovimiento: isset($datos['tarea_movimiento_id'])
                ? TareaMovimiento::query()->findOrFail($datos['tarea_movimiento_id'])
                : null,
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
            tareaMovimiento: isset($datos['tarea_movimiento_id'])
                ? TareaMovimiento::query()->findOrFail($datos['tarea_movimiento_id'])
                : null,
        );

        return (new MovimientoResource($this->cargarRelaciones($movimiento)))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function recientes(
        MovimientosRecientesRequest $request,
        AlcanceOperacionalUsuario $alcance,
    ): Response {
        $datos = $request->validated();
        $contenidos = collect($alcance->contenidosVisibles($request->user()))
            ->map->value
            ->all();
        $camaraId = $datos['camara_id'] ?? null;

        if ($camaraId) {
            $camara = Camara::query()->findOrFail($camaraId);
            abort_unless($alcance->puedeVerCamara($request->user(), $camara), 403);
        }

        $movimientos = Movimiento::query()
            ->whereHas('folio', fn ($consulta) => $consulta
                ->where('temporada_id', '=', $this->consultaTemporadaActiva()))
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
            ->latest('created_at')
            ->latest('id')
            ->limit($datos['limite'] ?? 3)
            ->get();

        $etag = $this->etagRecientes(
            $request,
            $movimientos,
            $contenidos,
            $camaraId,
            $datos['limite'] ?? 3,
        );
        $respuestaCondicional = $this->configurarCacheRecientes(response('', 200), $etag);

        if ($respuestaCondicional->isNotModified($request)) {
            return $respuestaCondicional;
        }

        $movimientos->load($this->relacionesMovimiento());

        return $this->configurarCacheRecientes(
            MovimientoResource::collection($movimientos)->response(),
            $etag,
        );
    }

    /**
     * @param  Collection<int, Movimiento>  $movimientos
     * @param  array<int, string>  $contenidos
     */
    private function etagRecientes(
        MovimientosRecientesRequest $request,
        Collection $movimientos,
        array $contenidos,
        ?string $camaraId,
        int $limite,
    ): string {
        $huella = json_encode([
            'usuario_id' => $request->user()?->getAuthIdentifier(),
            'contenidos' => $contenidos,
            'camara_id' => $camaraId,
            'limite' => $limite,
            // Movimiento es inalterable por dominio; sus atributos forman una
            // revisión estable sin cargar folio, usuario, cámaras ni posiciones.
            'movimientos' => $movimientos
                ->map(fn (Movimiento $movimiento): array => $movimiento->getAttributes())
                ->values()
                ->all(),
        ], JSON_THROW_ON_ERROR);

        return 'movimientos-recientes-'.hash('sha256', $huella);
    }

    private function configurarCacheRecientes(Response $respuesta, string $etag): Response
    {
        $respuesta->setEtag($etag);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-cache');
        $respuesta->setVary('Authorization');
        $respuesta->headers->set('Access-Control-Expose-Headers', 'ETag');

        return $respuesta;
    }

    private function cargarRelaciones(Movimiento $movimiento): Movimiento
    {
        return $movimiento->load($this->relacionesMovimiento());
    }

    private function consultaTemporadaActiva(): Builder
    {
        return Temporada::query()
            ->select('id')
            ->where('activa', true)
            ->limit(1);
    }

    /**
     * @return array{bool, string}
     */
    private function disponibilidadUbicacionInicial(
        Folio $folio,
        ServicioHabilitacionAlmacenamiento $habilitacion,
    ): array {
        if ($folio->ubicacionActual) {
            $ubicacion = $folio->ubicacionActual;
            $posicion = $ubicacion->posicion;
            $camara = $ubicacion->camara ?? $posicion?->camara;

            if ($folio->tipo_bulto === TipoBulto::Material && $camara && ! $posicion) {
                return [
                    true,
                    "El folio está en {$camara->codigo} sin posición. Puede completar una ubicación exacta.",
                ];
            }

            $detalle = $posicion?->etiqueta ? " · {$posicion->etiqueta}" : '';

            return [
                false,
                "El folio ya está ubicado en {$camara?->codigo}{$detalle}.",
            ];
        }

        try {
            $habilitacion->validarUbicacionInicial($folio);
        } catch (DomainException $exception) {
            return [false, $exception->getMessage()];
        }

        return [true, 'Folio habilitado para ingresar a cámara.'];
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
