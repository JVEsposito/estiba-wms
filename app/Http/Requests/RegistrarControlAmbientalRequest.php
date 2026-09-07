<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarControlAmbientalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('registrar-control-ambiental') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'temperatura_inicio_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'temperatura_medio_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'temperatura_fondo_c' => ['required', 'numeric', 'between:-999.99,999.99', 'decimal:0,2'],
            'capturado_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
