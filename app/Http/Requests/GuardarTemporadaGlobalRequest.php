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
            'intervalo_embarques_minutos' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'nombre' => trim((string) $this->input('nombre')),
            'fecha_inicio' => $this->filled('fecha_inicio') ? $this->input('fecha_inicio') : null,
            'fecha_fin' => $this->filled('fecha_fin') ? $this->input('fecha_fin') : null,
        ];

        if ($this->has('intervalo_embarques_minutos')) {
            $normalizados['intervalo_embarques_minutos'] = $this->integer('intervalo_embarques_minutos');
        }

        $this->merge($normalizados);
    }
}
