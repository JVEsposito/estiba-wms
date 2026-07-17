<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarFoliosPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folio' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', Rule::in([100, 250, 500, 1000])],
        ];
    }
}
