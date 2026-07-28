<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPerfilAccesoRequest;
use App\Models\PerfilAcceso;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PerfilAccesoController extends Controller
{
    public function __construct(
        private readonly CatalogoModulosAcceso $catalogo,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('administrar-accesos');

        return response()->json([
            'data' => PerfilAcceso::query()
                ->withCount('usuarios')
                ->orderByDesc('protegido')
                ->orderBy('nombre')
                ->get()
                ->map(fn (PerfilAcceso $perfil): array => $this->perfil($perfil)),
            'catalogo' => $this->catalogo->macromodulos(),
            'roles_base' => collect(RolUsuario::cases())
                ->reject(fn (RolUsuario $rol): bool => $rol === RolUsuario::Administrador)
                ->map(fn (RolUsuario $rol): array => [
                    'clave' => $rol->value,
                    'nombre' => $this->nombreRol($rol),
                    'modulos_disponibles' => $this->catalogo->modulosPredeterminados($rol),
                ])
                ->values(),
        ]);
    }

    public function store(GuardarPerfilAccesoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $perfil = PerfilAcceso::create([
            ...$datos,
            'predeterminado' => false,
            'protegido' => false,
            'creado_por_user_id' => $request->user()->id,
            'actualizado_por_user_id' => $request->user()->id,
        ]);

        return response()->json(
            ['data' => $this->perfil($perfil->loadCount('usuarios'))],
            Response::HTTP_CREATED,
        );
    }

    public function update(
        GuardarPerfilAccesoRequest $request,
        PerfilAcceso $perfilAcceso,
    ): JsonResponse {
        if ($perfilAcceso->protegido) {
            throw new DomainException(
                'El perfil Administrador está protegido. Puedes modificar todos los demás perfiles.',
            );
        }

        $datos = $request->validated();
        if ($perfilAcceso->predeterminado
            && $perfilAcceso->rol_base->value !== $datos['rol_base']) {
            throw new DomainException(
                'Puedes modificar los módulos del perfil inicial, pero no su nivel operacional base.',
            );
        }
        $resultado = DB::transaction(function () use ($datos, $perfilAcceso, $request): array {
            $perfil = PerfilAcceso::query()->lockForUpdate()->findOrFail($perfilAcceso->id);
            $seguridadCambio = $perfil->rol_base->value !== $datos['rol_base']
                || $perfil->modulos !== $datos['modulos']
                || $perfil->activo !== (bool) $datos['activo'];

            $perfil->fill([
                ...$datos,
                'actualizado_por_user_id' => $request->user()->id,
            ])->save();

            if ($seguridadCambio) {
                $usuarios = $perfil->usuarios()->lockForUpdate()->get();
                foreach ($usuarios as $usuario) {
                    $usuario->forceFill(['rol' => $perfil->rol_base])->save();
                    $usuario->tokens()->delete();
                }
            }

            return [
                'perfil' => $perfil->refresh()->loadCount('usuarios'),
                'sesiones_revocadas' => $seguridadCambio,
            ];
        }, attempts: 3);

        return response()->json([
            'data' => $this->perfil($resultado['perfil']),
            'sesiones_revocadas' => $resultado['sesiones_revocadas'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function perfil(PerfilAcceso $perfil): array
    {
        return [
            'id' => $perfil->id,
            'codigo' => $perfil->codigo,
            'nombre' => $perfil->nombre,
            'descripcion' => $perfil->descripcion,
            'rol_base' => $perfil->rol_base->value,
            'rol_base_nombre' => $this->nombreRol($perfil->rol_base),
            'modulos' => $perfil->modulos,
            'activo' => $perfil->activo,
            'predeterminado' => $perfil->predeterminado,
            'protegido' => $perfil->protegido,
            'usuarios_count' => (int) ($perfil->usuarios_count ?? 0),
            'actualizado_at' => $perfil->updated_at?->toAtomString(),
        ];
    }

    private function nombreRol(RolUsuario $rol): string
    {
        return match ($rol) {
            RolUsuario::Administrador => 'Administrador',
            RolUsuario::SupervisorFrio => 'Supervisor de frío',
            RolUsuario::SupervisorMateriales => 'Supervisor de materiales',
            RolUsuario::Despachador => 'Despachador',
            RolUsuario::OperadorPrefrio => 'Operador de prefrío',
            RolUsuario::OperadorRomana => 'Operador de romana',
            RolUsuario::DigitadorMateriaPrima => 'Digitador de materia prima',
            RolUsuario::CamareroFrio => 'Camarero de frío',
            RolUsuario::CamareroMateriales => 'Camarero de materiales',
            RolUsuario::Validador => 'Validador de pallets',
            RolUsuario::ValidadorMp => 'Validador MP',
            RolUsuario::Consulta => 'Solo consulta',
        };
    }
}
