<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoValidacionPallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarOpcionesRegistroValidacionRequest;
use App\Http\Requests\ConsultarValidacionesPalletRequest;
use App\Http\Requests\ExportarRegistroValidacionPalletRequest;
use App\Http\Requests\RegistrarValidacionPalletRequest;
use App\Http\Resources\ValidacionPalletResource;
use App\Models\Temporada;
use App\Models\ValidacionPallet;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Validacion\ServicioExportacionRegistroValidacion;
use App\Services\Validacion\ServicioValidacionPallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ValidacionPalletController extends Controller
{
    public function index(ConsultarValidacionesPalletRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $consulta = $this->aplicarFiltros(
            ValidacionPallet::query()->with($this->relaciones()),
            $filtros,
            $request->rangoFechaUtc(),
        )
            ->orderByDesc('recibido_servidor_at')
            ->orderByDesc('numero_intento');

        return ValidacionPalletResource::collection(
            $consulta->paginate($filtros['per_page'] ?? 25)->withQueryString(),
        );
    }

    public function opciones(ConsultarOpcionesRegistroValidacionRequest $request): JsonResponse
    {
        $temporadas = Temporada::query()
            ->orderByDesc('activa')
            ->orderByDesc('created_at')
            ->get(['id', 'codigo', 'nombre', 'activa']);
        $temporada = $request->filled('temporada_id')
            ? $temporadas->firstWhere('id', $request->validated('temporada_id'))
            : $temporadas->firstWhere('activa', true);

        $validadores = $temporada
            ? DB::table('validaciones_pallet as validaciones')
                ->join('users', 'users.id', '=', 'validaciones.user_id')
                ->where('validaciones.temporada_id', $temporada->id)
                ->select(['users.id', 'users.name'])
                ->distinct()
                ->orderBy('users.name')
                ->get()
                ->map(fn (object $usuario): array => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->name,
                ])
                ->values()
            : collect();

        return response()->json([
            'temporadas' => $temporadas,
            'temporada' => $temporada,
            'validadores' => $validadores,
        ]);
    }

    public function exportar(
        ExportarRegistroValidacionPalletRequest $request,
        ServicioExportacionRegistroValidacion $exportador,
    ): BinaryFileResponse {
        $filtros = $request->validated();
        $validaciones = $this->aplicarFiltros(
            ValidacionPallet::query()
                ->with(['usuario:id,name'])
                ->where('estado', EstadoValidacionPallet::Aceptada->value)
                ->whereNotNull('linea_proceso')
                ->whereNotNull('turno'),
            $filtros,
            $request->rangoFechaUtc(),
        )
            ->orderBy('generado_dispositivo_at')
            ->orderBy('numero_intento')
            ->get();

        $ruta = $exportador->generar($validaciones);
        $archivo = 'RRPP-01_'.$filtros['fecha'].'.xlsx';

        return response()->download(
            $ruta,
            $archivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
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

    /**
     * @param  Builder<ValidacionPallet>  $consulta
     * @param  array<string, mixed>  $filtros
     * @param  array{0:CarbonImmutable,1:CarbonImmutable}|null  $rangoFecha
     * @return Builder<ValidacionPallet>
     */
    private function aplicarFiltros(Builder $consulta, array $filtros, ?array $rangoFecha): Builder
    {
        return $consulta
            ->when(
                $filtros['temporada_id'] ?? null,
                fn (Builder $consulta, string $temporadaId): Builder => $consulta
                    ->where('temporada_id', $temporadaId),
                fn (Builder $consulta): Builder => $consulta->whereHas(
                    'temporada',
                    fn (Builder $temporada): Builder => $temporada->where('activa', true),
                ),
            )
            ->when(
                $filtros['folio'] ?? null,
                fn (Builder $consulta, string $folio): Builder => $consulta
                    ->where('numero_folio', $folio),
            )
            ->when(
                $filtros['resultado'] ?? null,
                fn (Builder $consulta, string $resultado): Builder => $consulta
                    ->where('resultado', $resultado),
            )
            ->when(
                $filtros['estado'] ?? null,
                fn (Builder $consulta, string $estado): Builder => $consulta
                    ->where('estado', $estado),
            )
            ->when(
                $filtros['linea_proceso'] ?? null,
                fn (Builder $consulta, int|string $linea): Builder => $consulta
                    ->where('linea_proceso', (int) $linea),
            )
            ->when(
                $filtros['turno'] ?? null,
                fn (Builder $consulta, string $turno): Builder => $consulta->where('turno', $turno),
            )
            ->when(
                $filtros['user_id'] ?? null,
                fn (Builder $consulta, int|string $usuarioId): Builder => $consulta
                    ->where('user_id', (int) $usuarioId),
            )
            ->when(
                $rangoFecha,
                fn (Builder $consulta, array $rango): Builder => $consulta
                    ->where('generado_dispositivo_at', '>=', $rango[0])
                    ->where('generado_dispositivo_at', '<', $rango[1]),
            );
    }
}
