<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarRetornoPackingRequest extends FormRequest
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
            'cierra_entrega' => ['required_without:entregas', 'boolean'],
            'entregas' => ['nullable', 'array', 'min:1', 'max:20'],
            'entregas.*.entrega_fruta_proceso_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('entregas_fruta_proceso', 'id'),
            ],
            'entregas.*.cierra_entrega' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'resultados' => ['required', 'array', 'min:1', 'max:20'],
            'resultados.*.tipo_resultado_packing_id' => [
                'required',
                'uuid',
                Rule::exists('tipos_resultado_packing', 'id')
                    ->where(fn ($consulta) => $consulta->where('activo', true)),
            ],
            'resultados.*.nombre_resultado' => ['nullable', 'string', 'max:100'],
            'resultados.*.cantidad_bins' => ['required', 'integer', 'min:1', 'max:100000'],
            'resultados.*.kilos_netos' => ['nullable', 'numeric', 'min:0.001', 'max:999999999.999'],
        ];
    }
}
