<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarIngresoRomanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['operacion_id' => ['required', 'uuid']];
    }
}
