<?php

namespace App\Http\Requests;

use App\Models\Temporada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarTemporadaGlobalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $temporada = $this->route('temporada');
        $temporadaId = $temporada instanceof Temporada ? $temporada->id : null;

        return [
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('temporadas', 'codigo')->ignore($temporadaId),
            ],
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'fecha_inicio' => $this->filled('fecha_inicio') ? $this->input('fecha_inicio') : null,
            'fecha_fin' => $this->filled('fecha_fin') ? $this->input('fecha_fin') : null,
        ]);
    }
}
