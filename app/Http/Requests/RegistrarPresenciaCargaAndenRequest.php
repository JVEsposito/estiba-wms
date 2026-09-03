<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarPresenciaCargaAndenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-cargas') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_esperada' => ['required', 'integer', 'min:1'],
            'anden_id' => [
                'required',
                'uuid',
                Rule::exists('andenes', 'id')->where('activo', true),
            ],
            'patente' => ['required', 'string', 'max:20'],
            'conductor' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'ingresada_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
