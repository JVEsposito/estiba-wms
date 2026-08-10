<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularBinRetornoPackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('anular-entregas-fruta-proceso') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
