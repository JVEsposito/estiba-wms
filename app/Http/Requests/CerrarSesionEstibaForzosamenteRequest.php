<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarSesionEstibaForzosamenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true
            && $this->user()->tokenCan('oficina');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo')),
        ]);
    }
}
