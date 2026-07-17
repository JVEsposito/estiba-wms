<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelarProcesoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supervisar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:3', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'ocurrido_at' => ['required', 'date'],
        ];
    }
}
