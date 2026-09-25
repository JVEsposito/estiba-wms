<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasificarTemporadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indica por qué cambia el tipo de la temporada.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
