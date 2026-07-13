<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccesoTabletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'codigo_dispositivo' => ['required', 'string', 'max:100'],
        ];
    }
}
