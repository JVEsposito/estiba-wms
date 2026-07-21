<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccesoOficinaRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccesoOficinaController extends Controller
{
    public function store(
        AccesoOficinaRequest $request,
        AlcanceOperacionalUsuario $alcance,
    ): JsonResponse {
        $datos = $request->validated();
        $usuario = User::query()
            ->where('email', mb_strtolower($datos['email']))
            ->first();

        if (! $usuario
            || ! $usuario->activo
            || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no son válidas.',
            ]);
        }

        if (! $alcance->puedeAccederOficina($usuario)) {
            throw ValidationException::withMessages([
                'email' => 'El usuario no posee acceso a los módulos de oficina.',
            ]);
        }

        $token = $usuario->createToken(
            'oficina-'.now()->format('Ymd-His'),
            ['oficina'],
        );

        $capacidades = $alcance->capacidadesApi($usuario);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol->value,
                'ambito_camaras' => $alcance->ambitoCamaras($usuario),
                'capacidades' => $capacidades,
                'puede_configurar_camaras' => $usuario->can('crear-camaras-productos')
                    || $usuario->can('crear-camaras-materiales'),
                'puede_crear_camaras_productos' => $usuario->can('crear-camaras-productos'),
                'puede_crear_camaras_materiales' => $usuario->can('crear-camaras-materiales'),
                'puede_administrar_camaras' => $usuario->can('administrar-camaras'),
                'puede_administrar_accesos' => $usuario->can('administrar-accesos'),
                'puede_gestionar_cargas' => $capacidades['puede_gestionar_cargas'],
                'puede_consultar_cargas' => $capacidades['puede_consultar_cargas'],
                'puede_consultar_catalogo_cargas' => $capacidades['puede_consultar_catalogo_cargas'],
                'puede_resolver_comercialmente_carga' => $capacidades['puede_resolver_comercialmente_carga'],
                'puede_resolver_reparacion_carga' => $capacidades['puede_resolver_reparacion_carga'],
                'puede_cerrar_despacho_frigorifico' => $capacidades['puede_cerrar_despacho_frigorifico'],
                'puede_gestionar_andenes' => $capacidades['puede_gestionar_andenes'],
                'puede_administrar_catalogos_materiales' => $usuario->can('administrar-catalogos-materiales'),
                'puede_gestionar_despachos_materiales' => $capacidades['puede_gestionar_despachos_materiales'],
                'puede_consultar_despachos_materiales' => $capacidades['puede_consultar_despachos_materiales'],
                'puede_cancelar_despachos_materiales' => $capacidades['puede_cancelar_despachos_materiales'],
                'puede_consultar_kardex_materiales' => $capacidades['puede_consultar_kardex_materiales'],
                'puede_consultar_validaciones_pallet' => $capacidades['puede_consultar_validaciones_pallet'],
                'puede_rechazar_pallets' => $capacidades['puede_rechazar_pallets'],
                'puede_administrar_catalogos_validacion' => $capacidades['puede_administrar_catalogos_validacion'],
                'puede_consultar_prefrio' => $capacidades['puede_consultar_prefrio'],
                'puede_operar_prefrio' => $capacidades['puede_operar_prefrio'],
                'puede_supervisar_prefrio' => $capacidades['puede_supervisar_prefrio'],
                'puede_administrar_tuneles_prefrio' => $capacidades['puede_administrar_tuneles_prefrio'],
                'puede_consultar_panel_gerencial' => $capacidades['puede_consultar_panel_gerencial'],
            ],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
