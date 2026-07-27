<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsociarProductorCsgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('asociar-productores-csg') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                'uuid',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
        ];
    }
}
