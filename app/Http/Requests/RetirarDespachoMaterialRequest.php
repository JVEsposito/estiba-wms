<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetirarDespachoMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-despachos-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'retiros' => ['required', 'array', 'min:1', 'max:100'],
            'retiros.*' => ['required', 'array:folio_id,cantidad,sesion_estiba_id'],
            'retiros.*.folio_id' => [
                'required',
                'uuid',
                'distinct',
                'exists:folios_materiales,folio_id',
            ],
            'retiros.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'retiros.*.sesion_estiba_id' => [
                'required',
                'uuid',
                'exists:sesiones_estiba,id',
            ],
        ];
    }
}
