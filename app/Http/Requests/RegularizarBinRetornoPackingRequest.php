<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegularizarBinRetornoPackingRequest extends FormRequest
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
            'folio_definitivo' => ['required', 'string', 'max:80'],
            'tipo_resultado_packing_id' => [
                'required',
                'uuid',
                Rule::exists('tipos_resultado_packing', 'id')
                    ->where(fn ($consulta) => $consulta->where('activo', true)),
            ],
            'nombre_resultado' => ['nullable', 'string', 'max:100'],
        ];
    }
}
