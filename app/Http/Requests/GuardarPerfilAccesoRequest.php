<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use App\Services\Autorizacion\CatalogoModulosAcceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GuardarPerfilAccesoRequest extends FormRequest
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
        $perfil = $this->route('perfilAcceso');
        $modulosPermitidos = app(CatalogoModulosAcceso::class)->claves();

        return [
            'codigo' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('perfiles_acceso', 'codigo')->ignore($perfil),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'rol_base' => [
                'required',
                Rule::enum(RolUsuario::class),
            ],
            'modulos' => ['required', 'array', 'min:1'],
            'modulos.*' => ['required', 'string', 'distinct', Rule::in($modulosPermitidos)],
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'Ingresa un código para el perfil.',
            'codigo.regex' => 'El código solo puede contener mayúsculas, números, guion y guion bajo.',
            'codigo.unique' => 'Ya existe un perfil con ese código.',
            'nombre.required' => 'Ingresa el nombre del perfil.',
            'rol_base.required' => 'Selecciona el nivel operacional base.',
            'rol_base.enum' => 'El nivel operacional seleccionado no es válido.',
            'modulos.required' => 'Selecciona al menos un módulo.',
            'modulos.min' => 'Selecciona al menos un módulo.',
            'modulos.*.in' => 'Uno de los módulos seleccionados no existe en el catálogo de accesos.',
            'activo.required' => 'Indica si el perfil se encuentra activo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'descripcion' => filled($this->input('descripcion'))
                ? trim((string) $this->input('descripcion'))
                : null,
            'modulos' => array_values(array_unique(array_filter(
                (array) $this->input('modulos', []),
                fn (mixed $modulo): bool => is_string($modulo) && $modulo !== '',
            ))),
        ]);
    }
}
