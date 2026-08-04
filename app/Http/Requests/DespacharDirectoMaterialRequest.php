<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DespacharDirectoMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-despachos-materiales') === true
            && $this->user()?->can('retirar-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'folio_id' => ['required', 'uuid', 'exists:folios_materiales,folio_id'],
            'destino_material_id' => ['required', 'uuid', 'exists:destinos_materiales,id'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }
}
