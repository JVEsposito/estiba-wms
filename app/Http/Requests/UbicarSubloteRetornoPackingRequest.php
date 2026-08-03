<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UbicarSubloteRetornoPackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'camara_id' => ['required', 'uuid', 'exists:camaras,id'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
