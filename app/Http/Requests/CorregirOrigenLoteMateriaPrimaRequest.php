<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorregirOrigenLoteMateriaPrimaRequest extends FormRequest
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
            'version_conocida' => ['required', 'integer', 'min:1'],
            'cuartel' => ['nullable', 'string', 'max:100'],
            'retirar_calibre' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cuartel' => filled($this->input('cuartel'))
                ? trim((string) $this->input('cuartel'))
                : null,
        ]);
    }
}
