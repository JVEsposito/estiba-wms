<?php

namespace App\Http\Requests;

use App\Models\ProveedorMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarProveedorMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-catalogos-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $proveedor = $this->route('proveedorMaterial');
        $proveedorId = $proveedor instanceof ProveedorMaterial ? $proveedor->id : null;

        return [
            'codigo' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('proveedores_materiales', 'codigo')->ignore($proveedorId),
            ],
            'nombre' => ['required', 'string', 'min:2', 'max:180'],
            'codigo_externo' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('proveedores_materiales', 'codigo_externo')->ignore($proveedorId),
            ],
            'activo' => ['required', 'boolean'],
            'cliente_ids' => ['required', 'array', 'min:1'],
            'cliente_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
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
