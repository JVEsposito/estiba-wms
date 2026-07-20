<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgregarFolioProcesoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:0'],
            'folio_id' => ['required', 'uuid', 'exists:folios,id'],
            'posicion_tunel_prefrio_id' => ['required', 'uuid', 'exists:posiciones_tunel_prefrio,id'],
            'temperatura_inicial' => ['nullable', 'numeric', 'between:-20,50'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'ocurrido_at' => ['required', 'date'],
        ];
    }
}
