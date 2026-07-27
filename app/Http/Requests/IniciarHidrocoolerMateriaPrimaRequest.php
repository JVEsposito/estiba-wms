<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IniciarHidrocoolerMateriaPrimaRequest extends FormRequest
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
            'equipo' => ['required', 'string', 'max:100'],
            'inicio_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['equipo' => trim((string) $this->input('equipo'))]);
    }
}
