<?php

namespace App\Http\Requests;

class ActualizarEmbarqueRequest extends GuardarEmbarqueRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version_esperada' => ['required', 'integer', 'min:1'],
        ];
    }
}
