<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibreValidacion;
use App\Models\CategoriaValidacion;
use App\Models\CsgValidacion;
use App\Models\EnvaseValidacion;
use App\Models\EspecieValidacion;
use App\Models\MarcaValidacion;
use App\Models\Temporada;
use App\Models\VariedadValidacion;
use App\Services\Validacion\ServicioCatalogoJerarquicoValidacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CatalogoJerarquicoValidacionController extends Controller
{
    public function index(
        Temporada $temporada,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json($servicio->datos($temporada));
    }

    public function storeMarca(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarMarca($this->datosHijo($request, 'cliente_validacion_id', 'clientes_validacion', 150)));
    }

    public function updateMarca(
        Request $request,
        MarcaValidacion $marcaValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarMarca(
            $this->datosHijo($request, 'cliente_validacion_id', 'clientes_validacion', 150),
            $marcaValidacion,
        )]);
    }

    public function storeEspecie(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarEspecie($this->datosRaiz($request, 100)));
    }

    public function updateEspecie(
        Request $request,
        EspecieValidacion $especieValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarEspecie(
            $this->datosRaiz($request, 100),
            $especieValidacion,
        )]);
    }

    public function storeCategoria(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarCategoria($this->datosRaiz($request, 100)));
    }

    public function updateCategoria(
        Request $request,
        CategoriaValidacion $categoriaValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarCategoria(
            $this->datosRaiz($request, 100),
            $categoriaValidacion,
        )]);
    }

    public function storeVariedad(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarVariedad($this->datosHijoEspecie($request, 100)));
    }

    public function updateVariedad(
        Request $request,
        VariedadValidacion $variedadValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarVariedad(
            $this->datosHijoEspecie($request, 100),
            $variedadValidacion,
        )]);
    }

    public function storeCalibre(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarCalibre($this->datosHijoEspecie($request, 50)));
    }

    public function updateCalibre(
        Request $request,
        CalibreValidacion $calibreValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarCalibre(
            $this->datosHijoEspecie($request, 50),
            $calibreValidacion,
        )]);
    }

    public function storeEnvase(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarEnvase($this->datosHijoEspecie($request, 100)));
    }

    public function updateEnvase(
        Request $request,
        EnvaseValidacion $envaseValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarEnvase(
            $this->datosHijoEspecie($request, 100),
            $envaseValidacion,
        )]);
    }

    public function storeCsg(Request $request, ServicioCatalogoJerarquicoValidacion $servicio): JsonResponse
    {
        return $this->creado($servicio->guardarCsg($this->datosCsg($request)));
    }

    public function updateCsg(
        Request $request,
        CsgValidacion $csgValidacion,
        ServicioCatalogoJerarquicoValidacion $servicio,
    ): JsonResponse {
        return response()->json(['data' => $servicio->guardarCsg(
            $this->datosCsg($request),
            $csgValidacion,
        )]);
    }

    private function creado($modelo): JsonResponse
    {
        return response()->json(['data' => $modelo], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function datosRaiz(Request $request, int $maximoNombre): array
    {
        return $request->validate([
            'temporada_id' => ['required', 'uuid', 'exists:temporadas,id'],
            'nombre' => ['required', 'string', "max:{$maximoNombre}"],
            'codigo_externo' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function datosHijoEspecie(Request $request, int $maximoNombre): array
    {
        return $this->datosHijo(
            $request,
            'especie_validacion_id',
            'especies_validacion',
            $maximoNombre,
        );
    }

    /** @return array<string, mixed> */
    private function datosHijo(
        Request $request,
        string $campo,
        string $tabla,
        int $maximoNombre,
    ): array {
        return $request->validate([
            $campo => ['required', 'uuid', "exists:{$tabla},id"],
            'nombre' => ['required', 'string', "max:{$maximoNombre}"],
            'codigo_externo' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function datosCsg(Request $request): array
    {
        return $request->validate([
            'temporada_id' => ['required', 'uuid', 'exists:temporadas,id'],
            'codigo' => ['required', 'string', 'max:50'],
            'predio' => ['nullable', 'string', 'max:150'],
            'codigo_externo' => ['nullable', 'string', 'max:100'],
            'variedad_ids' => ['required', 'array', 'min:1'],
            'variedad_ids.*' => ['uuid', 'distinct', 'exists:variedades_validacion,id'],
            'activo' => ['required', 'boolean'],
        ]);
    }
}
