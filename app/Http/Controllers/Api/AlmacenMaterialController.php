<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\EstadoPosicion;
use App\Enums\TipoMovimientoAlmacenMaterial;
use App\Http\Controllers\Controller;
use App\Models\Camara;
use App\Models\MovimientoAlmacenMaterial;
use App\Models\PersonalAccessToken;
use App\Services\Materiales\ServicioConsultaAlmacenesMaterial;
use App\Services\Materiales\ServicioMovimientoAlmacenMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AlmacenMaterialController extends Controller
{
    public function index(
        ServicioConsultaAlmacenesMaterial $consulta,
    ): JsonResponse {
        Gate::authorize('consultar-despachos-materiales');
        $existencias = $consulta->existencias();

        $camaras = Camara::query()
            ->with(['posiciones' => fn ($posiciones) => $posiciones
                ->where('estado', EstadoPosicion::Activa->value)
                ->orderBy('etiqueta')])
            ->where('contenido', ContenidoCamara::Materiales->value)
            ->where('estado', EstadoCamara::Activa->value)
            ->orderBy('codigo')
            ->get()
            ->map(fn (Camara $camara): array => [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
                'posiciones' => $camara->posiciones->map(fn ($posicion): array => [
                    'id' => $posicion->id,
                    'etiqueta' => $posicion->etiqueta,
                ])->values(),
            ])
            ->values();

        return response()->json([
            ...$existencias,
            'camaras' => $camaras,
        ]);
    }

    public function movimientos(
        Request $request,
        ServicioConsultaAlmacenesMaterial $consulta,
    ): JsonResponse {
        Gate::authorize('consultar-kardex-materiales');
        $datos = $request->validate([
            'limite' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'data' => $consulta->kardex((int) ($datos['limite'] ?? 250)),
        ]);
    }

    public function store(
        Request $request,
        ServicioMovimientoAlmacenMaterial $servicio,
    ): JsonResponse {
        $datos = $request->validate([
            'operacion_id' => ['required', 'uuid'],
            'tipo' => ['required', Rule::enum(TipoMovimientoAlmacenMaterial::class)],
            'folio_id' => ['required', 'uuid', 'exists:folios_materiales,folio_id'],
            'almacen_origen_id' => ['nullable', 'uuid', 'exists:destinos_materiales,id'],
            'almacen_destino_id' => ['nullable', 'uuid', 'exists:destinos_materiales,id'],
            'cantidad' => ['required', 'numeric', 'between:-99999999999.999,99999999999.999', 'not_in:0'],
            'motivo' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $request->input('tipo'),
                    [
                        TipoMovimientoAlmacenMaterial::Consumo->value,
                        TipoMovimientoAlmacenMaterial::Ajuste->value,
                    ],
                    true,
                )),
                'nullable',
                'string',
                'min:3',
                'max:1000',
            ],
            'motivo_excepcion_fifo' => ['nullable', 'string', 'min:5', 'max:1000'],
            'documento_relacionado' => ['nullable', 'string', 'max:150'],
            'camara_destino_id' => ['nullable', 'uuid', 'exists:camaras,id'],
            'posicion_destino_id' => ['nullable', 'uuid', 'exists:posiciones,id'],
        ]);
        $token = $request->user()->currentAccessToken();
        $dispositivo = $token instanceof PersonalAccessToken && $token->dispositivo_id
            ? $token->dispositivo()->first()
            : null;
        $movimiento = $servicio->registrar(
            $datos,
            $request->user(),
            $dispositivo,
        );

        return response()->json([
            'data' => $this->serializar($movimiento),
        ], Response::HTTP_CREATED);
    }

    private function serializar(MovimientoAlmacenMaterial $movimiento): array
    {
        return [
            'id' => $movimiento->id,
            'operacion_id' => $movimiento->operacion_id,
            'tipo' => $movimiento->tipo->value,
            'folio' => [
                'id' => $movimiento->folio_id,
                'numero_folio' => $movimiento->folioMaterial->folio->numero_folio,
            ],
            'item' => [
                'id' => $movimiento->item->id,
                'codigo' => $movimiento->item->codigo,
                'nombre' => $movimiento->item->nombre,
            ],
            'almacen_origen' => $movimiento->almacenOrigen ? [
                'id' => $movimiento->almacenOrigen->id,
                'codigo' => $movimiento->almacenOrigen->codigo,
                'nombre' => $movimiento->almacenOrigen->nombre,
            ] : null,
            'almacen_destino' => $movimiento->almacenDestino ? [
                'id' => $movimiento->almacenDestino->id,
                'codigo' => $movimiento->almacenDestino->codigo,
                'nombre' => $movimiento->almacenDestino->nombre,
            ] : null,
            'cantidad' => $movimiento->cantidad,
            'saldo_origen_resultante' => $movimiento->saldo_origen_resultante,
            'saldo_destino_resultante' => $movimiento->saldo_destino_resultante,
            'motivo' => $movimiento->motivo,
            'ocurrido_at' => $movimiento->ocurrido_at?->toAtomString(),
        ];
    }
}
