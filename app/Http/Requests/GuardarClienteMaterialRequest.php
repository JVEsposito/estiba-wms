<?php

namespace App\Http\Requests;

use App\Models\ClienteMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarClienteMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-catalogos-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $cliente = $this->route('clienteMaterial');
        $clienteId = $cliente instanceof ClienteMaterial ? $cliente->id : null;
        $temporadaId = (string) $this->input('temporada_material_id');

        return [
            'temporada_material_id' => ['required', 'uuid', 'exists:temporadas_materiales,id'],
            'codigo' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('clientes_materiales', 'codigo')
                    ->where(fn ($consulta) => $consulta->where('temporada_material_id', $temporadaId))
                    ->ignore($clienteId),
            ],
            'nombre' => ['required', 'string', 'min:2', 'max:180'],
            'codigo_externo' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique('clientes_materiales', 'codigo_externo')
                    ->where(fn ($consulta) => $consulta->where('temporada_material_id', $temporadaId))
                    ->ignore($clienteId),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'codigo_externo' => $this->filled('codigo_externo')
                ? trim((string) $this->input('codigo_externo'))
                : null,
        ]);
    }
}
