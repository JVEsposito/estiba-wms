<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularEntregaFrutaProcesoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
