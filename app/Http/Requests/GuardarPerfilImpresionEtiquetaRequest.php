<?php

namespace App\Http\Requests;

use App\Models\PerfilImpresionEtiqueta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPerfilImpresionEtiquetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $perfil = $this->route('perfilImpresionEtiqueta');
        $perfilId = $perfil instanceof PerfilImpresionEtiqueta ? $perfil->id : null;

        return [
            'codigo' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('perfiles_impresion_etiquetas', 'codigo')->ignore($perfilId),
            ],
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'fabricante' => ['required', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'lenguaje' => ['required', Rule::in(['zpl', 'bpl-z'])],
            'dpi' => ['required', 'integer', Rule::in([203, 300, 600])],
            'ancho_mm' => ['required', 'numeric', 'between:30,200', 'decimal:0,2'],
            'alto_mm' => ['required', 'numeric', 'between:20,150', 'decimal:0,2'],
            'orientacion' => ['required', Rule::in(['horizontal', 'vertical'])],
            'predeterminado' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'fabricante' => trim((string) $this->input('fabricante')),
            'modelo' => $this->filled('modelo')
                ? trim((string) $this->input('modelo'))
                : null,
            'lenguaje' => mb_strtolower(trim((string) $this->input('lenguaje', 'zpl'))),
            'orientacion' => mb_strtolower(trim((string) $this->input('orientacion', 'horizontal'))),
            'predeterminado' => $this->boolean('predeterminado'),
            'activo' => $this->boolean('activo'),
        ]);
    }
}
