<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarOrdenTransformacionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-transformaciones-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:1'],
            'motivo_desviacion' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
