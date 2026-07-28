<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsociarProductorCsgRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('cliente_ids') && $this->filled('cliente_id')) {
            $this->merge(['cliente_ids' => [$this->input('cliente_id')]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('asociar-productores-csg') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_ids' => ['required', 'array', 'min:1', 'max:100'],
            'cliente_ids.*' => [
                'distinct',
                'required',
                'uuid',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
        ];
    }
}
