<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoverFolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'folio_id' => ['required', 'uuid', 'exists:folios,id'],
            'posicion_destino_id' => ['required', 'uuid', 'exists:posiciones,id'],
            'sesion_origen_id' => ['required', 'uuid', 'exists:sesiones_estiba,id'],
            'sesion_destino_id' => ['required', 'uuid', 'exists:sesiones_estiba,id'],
            'version_origen_conocida' => ['required', 'integer', 'min:0'],
            'version_destino_conocida' => ['required', 'integer', 'min:0'],
            'generado_dispositivo_at' => ['required', 'date'],
        ];
    }
}
