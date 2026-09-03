<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TareaMovimientoResource;
use App\Models\TareaMovimiento;
use App\Services\Autenticacion\ContextoOperacional;
use App\Services\Cargas\ServicioPlanDespachoDirecto;
use Illuminate\Http\Request;

class DespachoDirectoPlanificadorController extends Controller
{
    public function completarPrefrio(
        Request $request,
        TareaMovimiento $tareaMovimiento,
        ContextoOperacional $contexto,
        ServicioPlanDespachoDirecto $servicio,
    ): TareaMovimientoResource {
        [$usuario, $dispositivo] = $contexto->obtener($request);

        return new TareaMovimientoResource(
            $servicio->completarDesdePrefrio(
                $tareaMovimiento,
                $usuario,
                $dispositivo,
            )->load([
                'planOperacional:id,temporada_id,tipo,estado,prioridad,titulo,version,contexto',
                'folio:id,numero_folio,tipo_bulto',
                'camaraOrigen:id,nombre',
                'posicionOrigen:id,camara_id,etiqueta,banda,posicion,nivel',
                'camaraDestino:id,nombre',
                'posicionDestino:id,camara_id,etiqueta,banda,posicion,nivel',
                'responsable:id,name',
                'dispositivo:id,codigo,nombre',
                'reservaActiva:id,tarea_movimiento_id,bloqueo_tarea_id,bloqueo_posicion_id,estado,reservada_at,renovada_at,vence_at,version',
            ]),
        );
    }
}
