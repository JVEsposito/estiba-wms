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
            'folio_id' => ['required_without:retiros', 'prohibited_with:retiros', 'uuid', 'exists:folios_materiales,folio_id'],
            'destino_material_id' => ['required', 'uuid', 'exists:destinos_materiales,id'],
            'cantidad' => ['required_with:folio_id', 'prohibited_with:retiros', 'numeric', 'gt:0', 'decimal:0,3'],
            'retiros' => ['required_without:folio_id', 'prohibited_with:folio_id', 'array', 'min:1', 'max:100'],
            'retiros.*' => ['required', 'array:folio_id,cantidad'],
            'retiros.*.folio_id' => ['required', 'uuid', 'distinct', 'exists:folios_materiales,folio_id'],
            'retiros.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'motivo_excepcion_fifo' => ['nullable', 'string', 'min:5', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
            'motivo_excepcion_fifo' => $this->filled('motivo_excepcion_fifo')
                ? trim((string) $this->input('motivo_excepcion_fifo'))
                : null,
        ]);
    }
}
