<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPesajeEnvasesRomanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'cantidad_envases' => ['required', 'integer', 'min:1', 'max:100000'],
            'peso_bruto' => ['required', 'numeric', 'min:0.001', 'max:200000', 'decimal:0,3'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cantidad_envases.required' => 'Indica cuántos envases contiene esta lectura.',
            'cantidad_envases.min' => 'Cada lectura debe contener al menos un envase.',
            'peso_bruto.required' => 'Ingresa el peso bruto observado para este grupo.',
            'peso_bruto.min' => 'El peso bruto de la lectura debe ser mayor que cero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion' => filled($this->input('observacion'))
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }
}
