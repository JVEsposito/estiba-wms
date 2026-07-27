<?php

namespace App\Http\Requests;

class ActualizarRecepcionMaterialRequest extends CrearRecepcionMaterialRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version_conocida' => ['required', 'integer', 'min:1'],
        ];
    }
}
