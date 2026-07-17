<?php

namespace App\Http\Requests;

use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarAndenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && app(AlcanceOperacionalUsuario::class)->puedeGestionarAndenes($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9-]*$/',
                Rule::unique('andenes', 'codigo')->ignore($this->route('anden')),
            ],
            'nombre' => ['required', 'string', 'max:100'],
            'codigo_externo' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => is_string($this->input('codigo'))
                ? strtoupper(trim($this->input('codigo')))
                : $this->input('codigo'),
            'nombre' => is_string($this->input('nombre'))
                ? trim($this->input('nombre'))
                : $this->input('nombre'),
            'codigo_externo' => $this->textoOpcional($this->input('codigo_externo')),
        ]);
    }

    private function textoOpcional(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }
}
