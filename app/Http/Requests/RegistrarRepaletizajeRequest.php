<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarRepaletizajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tipo_resultado' => ['required', Rule::in(['pallet', 'saldo'])],
            'estrategia_folio' => ['required', Rule::in(['conservar', 'nuevo'])],
            'numero_folio_resultante' => ['required', 'string', 'max:80'],
            'folio_conservado_id' => [
                'nullable',
                'uuid',
                'required_if:estrategia_folio,conservar',
                Rule::exists('folios', 'id'),
            ],
            'cantidad_objetivo' => [
                'nullable',
                'integer',
                'min:2',
                'max:100000',
                'required_if:tipo_resultado,pallet',
            ],
            'origenes' => ['required', 'array', 'min:2', 'max:20'],
            'origenes.*.folio_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('folios', 'id'),
            ],
            'origenes.*.cantidad_aportada' => ['required', 'integer', 'min:1', 'max:100000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
