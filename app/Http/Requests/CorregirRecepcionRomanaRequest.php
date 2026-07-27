<?php

namespace App\Http\Requests;

use App\Enums\TipoEnvaseRomana;
use Illuminate\Validation\Rule;

class CorregirRecepcionRomanaRequest extends CrearRecepcionRomanaRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('corregir-recepciones-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version_conocida' => ['required', 'integer', 'min:1'],
            'motivo_correccion' => ['required', 'string', 'min:5', 'max:1000'],
            'peso_tara' => ['nullable', 'numeric', 'min:1', 'max:200000', 'decimal:0,2'],
            'tipo_envase_calculo_neto' => ['nullable', Rule::enum(TipoEnvaseRomana::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'version_conocida.required' => 'Actualiza el expediente antes de corregir la recepción.',
            'motivo_correccion.required' => 'Explica el motivo de la corrección administrativa.',
            'motivo_correccion.min' => 'El motivo de corrección debe tener al menos 5 caracteres.',
            'peso_tara.max' => 'La tara supera el máximo operacional de 200.000 kg.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'motivo_correccion' => trim((string) $this->input('motivo_correccion')),
        ]);
    }
}
