<?php

namespace App\Http\Requests;

class AdministrarRecepcionMaterialRequest extends ActualizarRecepcionMaterialRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-recepciones-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'motivo_correccion' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmacion_operacion_id' => ['nullable', 'uuid'],
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
