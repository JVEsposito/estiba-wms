<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ActualizarCargaRequest extends CrearCargaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();
        $reglas['numero_orden_externa'] = [
            'nullable',
            'string',
            'max:100',
            Rule::unique('cargas', 'numero_orden_externa')
                ->ignore($this->route('carga')),
        ];
        $reglas['version_esperada'] = ['required', 'integer', 'min:1'];

        return $reglas;
    }
}
