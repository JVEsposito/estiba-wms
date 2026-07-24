<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearRecetaMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-recetas-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('activo', true)],
            'item_salida_id' => ['required', 'uuid', 'exists:items_materiales,id'],
            'nombre' => ['required', 'string', 'min:3', 'max:180'],
            'cantidad_base_salida' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'componentes' => ['required', 'array', 'min:1', 'max:50'],
            'componentes.*' => [
                'required',
                'array:item_entrada_id,cantidad_estandar,es_componente_principal,factor_conversion,merma_estandar_porcentaje,tolerancia_porcentaje',
            ],
            'componentes.*.item_entrada_id' => [
                'required',
                'uuid',
                'distinct',
                'exists:items_materiales,id',
            ],
            'componentes.*.cantidad_estandar' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'componentes.*.es_componente_principal' => ['required', 'boolean'],
            'componentes.*.factor_conversion' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,6'],
            'componentes.*.merma_estandar_porcentaje' => [
                'sometimes',
                'numeric',
                'between:0,100',
                'decimal:0,4',
            ],
            'componentes.*.tolerancia_porcentaje' => [
                'sometimes',
                'numeric',
                'between:0,100',
                'decimal:0,4',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }
}
