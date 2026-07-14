<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VersionCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-cargas') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version_esperada' => ['required', 'integer', 'min:1'],
            'motivo' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
