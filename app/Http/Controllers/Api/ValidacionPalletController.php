<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarValidacionesPalletRequest;
use App\Http\Requests\RegistrarValidacionPalletRequest;
use App\Http\Resources\ValidacionPalletResource;
use App\Models\ValidacionPallet;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Validacion\ServicioValidacionPallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ValidacionPalletController extends Controller
{
    public function index(ConsultarValidacionesPalletRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $consulta = ValidacionPallet::query()
            ->with($this->relaciones())
            ->when(
                $filtros['temporada_id'] ?? null,
                fn ($consulta, string $temporadaId) => $consulta
                    ->where('temporada_id', $temporadaId),
                fn ($consulta) => $consulta->whereHas(
                    'temporada',
                    fn ($temporada) => $temporada->where('activa', true),
                ),
            )
            ->when(
                $filtros['folio'] ?? null,
                fn ($consulta, string $folio) => $consulta->where('numero_folio', $folio),
            )
            ->when(
                $filtros['resultado'] ?? null,
                fn ($consulta, string $resultado) => $consulta->where('resultado', $resultado),
            )
            ->when(
                $filtros['estado'] ?? null,
                fn ($consulta, string $estado) => $consulta->where('estado', $estado),
            )
            ->latest('recibido_servidor_at');

        return ValidacionPalletResource::collection(
            $consulta->paginate($filtros['per_page'] ?? 25)->withQueryString(),
        );
    }

    public function show(ValidacionPallet $validacionPallet): ValidacionPalletResource
    {
        return new ValidacionPalletResource(
            $validacionPallet->load($this->relaciones()),
        );
    }

    public function store(
        RegistrarValidacionPalletRequest $request,
        ContextoOperacional $contexto,
        ServicioValidacionPallet $servicio,
    ): JsonResponse {
        [$usuario, $dispositivo] = $contexto->obtener($request);
        [$validacion, $creada, $conflicto] = $servicio->registrar($request->validated(), $usuario, $dispositivo);

        $estado = $conflicto
            ? Response::HTTP_CONFLICT
            : ($creada ? Response::HTTP_CREATED : Response::HTTP_OK);

        return (new ValidacionPalletResource($validacion))
            ->additional([
                'catalogo_desactualizado' => $validacion->catalogo_version_dispositivo !== $validacion->catalogo_version_servidor,
                'message' => $conflicto
                    ? 'El folio ya posee una decisión final o existe en inventario. La contradicción quedó auditada.'
                    : null,
            ])
            ->response()
            ->setStatusCode($estado);
    }

    /**
     * @return array<int, string>
     */
    private function relaciones(): array
    {
        return [
            'temporada:id,codigo,nombre,activa',
            'folio:id,numero_folio,estado_operacional',
            'usuario:id,name',
            'dispositivo:id,codigo,nombre',
            'conflictoCon:id,numero_folio,numero_intento,resultado',
        ];
    }
}
