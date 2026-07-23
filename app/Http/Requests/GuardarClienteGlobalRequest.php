<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarClienteGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $clienteId = $cliente instanceof Cliente ? $cliente->id : null;

        return [
            'codigo' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('clientes', 'codigo')->ignore($clienteId),
            ],
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:180',
                Rule::unique('clientes', 'nombre')->ignore($clienteId),
            ],
            'codigo_externo' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('clientes', 'codigo_externo')->ignore($clienteId),
            ],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'codigo_externo' => $this->filled('codigo_externo')
                ? trim((string) $this->input('codigo_externo'))
                : null,
            'activo' => $this->boolean('activo'),
        ]);
    }
}
