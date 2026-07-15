<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CrearUsuarioAdministracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('administrar-accesos');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
