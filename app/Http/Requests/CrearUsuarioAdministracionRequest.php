<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
                'bail',
                'required',
                'string',
                'confirmed',
                'min:10',
                'regex:/^(?=.*\p{L})(?=.*\p{N})/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre completo.',
            'nombre.string' => 'El nombre completo debe ser texto.',
            'nombre.max' => 'El nombre completo no puede superar 255 caracteres.',
            'email.required' => 'Ingresa el correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar 255 caracteres.',
            'email.unique' => 'Ese correo electrónico ya está registrado.',
            'rol.required' => 'Selecciona un rol.',
            'rol.enum' => 'El rol seleccionado no es válido.',
            'password.required' => 'Ingresa una contraseña temporal.',
            'password.string' => 'La contraseña temporal debe ser texto.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra y un número.',
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
