<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarHidrocoolerMateriaPrimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-hidrocooler-materia-prima') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'equipo' => ['required', 'string', 'max:100'],
            'turno' => ['required', Rule::in(['A', 'B'])],
            'cantidad_bombas_funcionando' => ['required', 'integer', 'between:1,20'],
            'inicio_at' => ['required', 'date', 'before_or_equal:now'],
            'temperatura_inicial_c' => ['required', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'temperatura_objetivo_c' => ['required', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'temperatura_agua_inicial_c' => ['nullable', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'cloro_libre_ppm' => ['required', 'numeric', 'between:0,500', 'decimal:0,2'],
            'ph_agua' => ['required', 'numeric', 'between:0,14', 'decimal:0,2'],
            'condicion_visual_agua' => ['required', Rule::in(['conforme', 'no_conforme'])],
            'dosificador_operativo' => ['required', 'boolean'],
            'manejo_agua' => ['required', Rule::in(['sin_novedad', 'filtrado', 'recambio'])],
            'observacion_inicio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'equipo' => trim((string) $this->input('equipo')),
            'turno' => strtoupper(trim((string) $this->input('turno'))),
            'observacion_inicio' => filled($this->input('observacion_inicio'))
                ? trim((string) $this->input('observacion_inicio'))
                : null,
        ]);
    }
}
