<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovimientosRecientesRequest extends FormRequest
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
            'camara_id' => ['nullable', 'uuid', 'exists:camaras,id'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
