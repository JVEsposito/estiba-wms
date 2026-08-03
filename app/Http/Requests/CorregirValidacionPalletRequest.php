<?php

namespace App\Http\Requests;

use App\Enums\TipoBulto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorregirValidacionPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('corregir-validaciones-pallet') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tipo_bulto' => [
                'required',
                Rule::in([TipoBulto::Pallet->value, TipoBulto::Saldo->value]),
            ],
            'cantidad_cajas' => ['required', 'integer', 'min:1'],
            'linea_proceso' => ['required', 'integer', Rule::in([1, 2, 3])],
            'turno' => ['required', 'string', Rule::in(['A', 'B'])],
            'articulo_validacion_id' => [
                'required',
                'uuid',
                'exists:articulos_validacion,id',
            ],
            'origen_validacion_id' => [
                'required',
                'uuid',
                'exists:origenes_validacion,id',
            ],
            'categoria_validacion_id' => [
                'required',
                'uuid',
                'exists:categorias_validacion,id',
            ],
            'motivo_correccion' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'turno' => mb_strtoupper(trim((string) $this->input('turno'))),
            'motivo_correccion' => trim((string) $this->input('motivo_correccion')),
        ]);
    }
}
