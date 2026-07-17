<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccionProcesoPrefrioRequest extends FormRequest
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
            'observacion' => ['nullable', 'string', 'max:2000'],
            'datos' => ['nullable', 'array', 'max:50'],
            'ocurrido_at' => ['required', 'date'],
        ];
    }
}
