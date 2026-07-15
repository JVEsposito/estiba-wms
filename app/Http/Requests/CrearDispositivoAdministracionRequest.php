<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CrearDispositivoAdministracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('administrar-accesos');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9][A-Z0-9-]{2,99}$/',
                Rule::unique('dispositivos', 'codigo'),
            ],
            'nombre' => ['required', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
        ]);
    }
}
