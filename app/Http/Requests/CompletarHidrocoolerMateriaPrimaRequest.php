<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompletarHidrocoolerMateriaPrimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-lotes-materia-prima') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'termino_at' => ['required', 'date', 'before_or_equal:now'],
            'temperatura_c' => ['required', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'observacion' => ['nullable', 'string', 'max:2000'],
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
