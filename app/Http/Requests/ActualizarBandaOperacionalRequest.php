<?php

namespace App\Http\Requests;

use App\Enums\ModoBandaOperacional;
use App\Enums\UsoBandaOperacional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarBandaOperacionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-camaras') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'usos_permitidos' => ['required', 'array', 'min:1', 'max:3'],
            'usos_permitidos.*' => [
                'required',
                'distinct',
                Rule::enum(UsoBandaOperacional::class),
            ],
            'modo' => ['required', Rule::enum(ModoBandaOperacional::class)],
            'motivo_estado' => [
                Rule::requiredIf(fn (): bool => $this->input('modo') !== ModoBandaOperacional::Operativa->value),
                'nullable',
                'string',
                'min:3',
                'max:500',
            ],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
