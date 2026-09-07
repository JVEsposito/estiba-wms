<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorregirControlAmbientalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('corregir-control-ambiental') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:0'],
            'temperatura_inicio_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'temperatura_medio_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'temperatura_fondo_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo')),
        ]);
    }
}
