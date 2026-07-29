<?php

namespace App\Http\Requests;

use App\Enums\TipoEnvaseRomana;
use App\Enums\TipoRecepcionRomana;
use App\Models\RecepcionRomana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CerrarRecepcionRomanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $recepcion = $this->route('recepcion');
        $esPesajeEnvases = $recepcion instanceof RecepcionRomana
            && $recepcion->tipo_recepcion === TipoRecepcionRomana::FrutaPesajeEnvases;

        return [
            'operacion_id' => ['required', 'uuid'],
            'peso_tara' => [
                'nullable',
                Rule::requiredIf(! $esPesajeEnvases),
                'numeric',
                'min:1',
                'max:200000',
                'decimal:0,2',
            ],
            'tipo_envase_calculo_neto' => ['nullable', Rule::enum(TipoEnvaseRomana::class)],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'peso_tara.required' => 'Ingresa la tara capturada en el destare.',
            'peso_tara.max' => 'La tara supera el máximo operacional de 200.000 kg.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion' => filled($this->input('observacion')) ? trim((string) $this->input('observacion')) : null,
        ]);
    }
}
