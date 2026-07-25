<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GestionarBloqueoMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-bloqueos-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
