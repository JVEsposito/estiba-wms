<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevertirLoteTransformacionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('revertir-transformaciones-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
