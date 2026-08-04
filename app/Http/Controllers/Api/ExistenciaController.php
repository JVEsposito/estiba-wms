<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConexionExistencia;
use App\Services\Existencias\GeneradorLibroXlsx;
use App\Services\Existencias\ServicioExistencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExistenciaController extends Controller
{
    public function index(Request $request, ServicioExistencias $servicio): JsonResponse
    {
        $usuario = $request->user();
        $tipo = trim((string) $request->query('tipo', ''));

        if ($tipo !== '') {
            $servicio->definicion($tipo);
            abort_unless($servicio->puedeConsultar($usuario, $tipo), Response::HTTP_FORBIDDEN);
        }

        $historial = ConexionExistencia::query()
            ->where('user_id', $usuario->id)
            ->when($tipo !== '', fn ($consulta) => $consulta->where('tipo', $tipo))
            ->latest()
            ->limit(30)
            ->get();
        $conexionesActivas = $historial
            ->filter(fn (ConexionExistencia $conexion): bool => $conexion->estaVigente())
            ->groupBy('tipo');

        $tipos = collect($servicio->disponiblesPara($usuario))
            ->when(
                $tipo !== '',
                fn ($definiciones) => $definiciones->where('tipo', $tipo),
            )
            ->map(function (array $definicion) use ($conexionesActivas): array {
                unset($definicion['columnas']);
                $definicion['conexiones_activas'] = $conexionesActivas
                    ->get($definicion['tipo'], collect())
                    ->count();

                return $definicion;
            })
            ->values();

        return response()->json([
            'data' => $tipos,
            'conexiones' => $historial
                ->map(fn (ConexionExistencia $conexion): array => $this->serializarConexion($conexion))
                ->values(),
        ]);
    }

    public function corte(
        Request $request,
        string $tipo,
        ServicioExistencias $servicio,
        GeneradorLibroXlsx $generador,
    ): BinaryFileResponse {
        $usuario = $request->user();
        abort_unless($servicio->puedeConsultar($usuario, $tipo), Response::HTTP_FORBIDDEN);

        $definicion = $servicio->definicion($tipo);
        $filas = $servicio->filas($tipo);
        $ruta = $generador->generar(
            $definicion['titulo'],
            $this->columnasExcel($definicion['columnas']),
            $filas,
            [
                'fecha_corte' => now()->toAtomString(),
                'usuario' => $usuario->name,
                'temporada' => $servicio->temporadaActiva() ?? 'Temporada activa',
            ],
        );
        $archivo = $definicion['archivo'].'_'.now()->format('Y-m-d_Hi').'.xlsx';

        return response()->download(
            $ruta,
            $archivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    public function crearConexion(
        Request $request,
        string $tipo,
        ServicioExistencias $servicio,
    ): Response {
        $usuario = $request->user();
        abort_unless($servicio->puedeConsultar($usuario, $tipo), Response::HTTP_FORBIDDEN);
        $definicion = $servicio->definicion($tipo);
        $token = Str::random(80);
        $conexion = ConexionExistencia::create([
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'token_hash' => hash('sha256', $token),
            'expira_at' => now()->addYear(),
        ]);
        $url = url('/api/existencias/'.$tipo.'/consulta').'?token='.rawurlencode($token);
        $contenido = implode("\r\n", [
            'WEB',
            '1',
            $url,
            '',
            'Selection=1',
            'Formatting=None',
            'PreFormattedTextToColumns=True',
            'ConsecutiveDelimitersAsOne=True',
            'SingleBlockTextImport=False',
            'DisableDateRecognition=False',
            'DisableRedirections=False',
            '',
        ]);
        $archivo = $definicion['archivo'].'_Autoactualizable_'.$conexion->id.'.iqy';

        return response($contenido, Response::HTTP_CREATED, [
            'Content-Type' => 'application/x-msquery; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
            'Cache-Control' => 'no-store, private',
            'X-Conexion-Existencia' => $conexion->id,
        ]);
    }

    public function consulta(
        Request $request,
        string $tipo,
        ServicioExistencias $servicio,
    ): StreamedResponse {
        $token = (string) $request->query('token', '');
        abort_if($token === '', Response::HTTP_UNAUTHORIZED, 'La conexión de Excel no posee un token válido.');

        $conexion = ConexionExistencia::query()
            ->with('usuario')
            ->where('token_hash', hash('sha256', $token))
            ->where('tipo', $tipo)
            ->first();

        abort_unless($conexion, Response::HTTP_UNAUTHORIZED, 'La conexión de Excel no existe.');
        abort_unless($conexion->estaVigente(), Response::HTTP_GONE, 'La conexión de Excel venció o fue revocada.');
        abort_unless(
            $servicio->puedeConsultar($conexion->usuario, $tipo),
            Response::HTTP_FORBIDDEN,
            'El usuario ya no posee permiso para consultar esta existencia.',
        );

        $definicion = $servicio->definicion($tipo);
        $conexion->forceFill(['ultimo_uso_at' => now()])->save();
        $fecha = now()->toAtomString();

        return response()->stream(function () use ($servicio, $tipo, $definicion, $conexion, $fecha): void {
            echo $this->tablaHtmlInicio(
                $definicion['titulo'],
                $definicion['columnas'],
                $conexion->usuario->name,
                $fecha,
            );
            foreach ($servicio->filas($tipo) as $fila) {
                echo $this->filaHtml($definicion['columnas'], $fila);
            }
            echo $this->tablaHtmlFin();
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function revocar(
        Request $request,
        ConexionExistencia $conexionExistencia,
    ): JsonResponse {
        abort_unless(
            $conexionExistencia->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
        );
        $conexionExistencia->forceFill(['revocado_at' => now()])->save();

        return response()->json([
            'data' => $this->serializarConexion($conexionExistencia->refresh()),
        ]);
    }

    /**
     * @param  array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>  $columnas
     * @return array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>
     */
    private function columnasExcel(array $columnas): array
    {
        $fechas = [
            'fecha_cosecha',
            'fecha_fabricacion',
            'fecha_vencimiento',
        ];
        $fechasHora = [
            'fecha_ingreso',
            'ultima_actualizacion',
            'inicio_hidrocooler',
            'termino_hidrocooler',
            'asignado_at',
            'confirmado_at',
        ];

        return collect($columnas)
            ->map(function (array $columna) use ($fechas, $fechasHora): array {
                if (isset($columna['tipo'])) {
                    return $columna;
                }

                if (in_array($columna['clave'], $fechas, true)) {
                    $columna['tipo'] = 'fecha';
                } elseif (in_array($columna['clave'], $fechasHora, true)) {
                    $columna['tipo'] = 'fecha_hora';
                }

                return $columna;
            })
            ->all();
    }

    /**
     * @param  array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>  $columnas
     */
    private function tablaHtmlInicio(
        string $titulo,
        array $columnas,
        string $usuario,
        string $fecha,
    ): string {
        $cabeceras = collect($columnas)
            ->map(fn (array $columna): string => '<th>'.e($columna['titulo']).'</th>')
            ->implode('');
        $tituloSeguro = e($titulo);
        $usuarioSeguro = e($usuario);
        $fechaSegura = e($fecha);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{$tituloSeguro}</title>
<style>
body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#111}h1{font-size:16pt}p{margin:2px 0 10px;color:#444}table{border-collapse:collapse}th,td{border:1px solid #8a8a8a;padding:5px;white-space:nowrap}th{background:#c9aa68;color:#241d10;font-weight:700}.numero{text-align:right}
</style>
</head>
<body>
<h1>{$tituloSeguro}</h1>
<p>Actualizado: {$fechaSegura} · Conexión de {$usuarioSeguro}</p>
<table id="Existencia"><thead><tr>{$cabeceras}</tr></thead><tbody>
HTML;
    }

    /**
     * @param  array<int, array{clave:string,titulo:string,ancho?:int,tipo?:string}>  $columnas
     * @param  array<string, mixed>  $fila
     */
    private function filaHtml(array $columnas, array $fila): string
    {
        $celdas = '';
        foreach ($columnas as $columna) {
            $valor = $fila[$columna['clave']] ?? '';
            $clase = ($columna['tipo'] ?? 'texto') === 'numero' ? ' class="numero"' : '';
            $celdas .= '<td'.$clase.'>'.e($valor === null ? '' : (string) $valor).'</td>';
        }

        return '<tr>'.$celdas.'</tr>';
    }

    private function tablaHtmlFin(): string
    {
        return <<<'HTML'
</tbody></table>
</body>
</html>
HTML;
    }

    /** @return array<string, mixed> */
    private function serializarConexion(ConexionExistencia $conexion): array
    {
        return [
            'id' => $conexion->id,
            'tipo' => $conexion->tipo,
            'vigente' => $conexion->estaVigente(),
            'expira_at' => $conexion->expira_at?->toAtomString(),
            'ultimo_uso_at' => $conexion->ultimo_uso_at?->toAtomString(),
            'revocado_at' => $conexion->revocado_at?->toAtomString(),
            'created_at' => $conexion->created_at?->toAtomString(),
        ];
    }
}
