<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegularizarItemMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-catalogos-materiales') === true
            && $this->user()?->can('administrar-recetas-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'item_canonico_id' => ['required', 'uuid', 'exists:items_materiales,id'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
