<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorregirItemFolioMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('corregir-items-estibados-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'item_material_id' => ['required', 'uuid', 'exists:items_materiales,id'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
