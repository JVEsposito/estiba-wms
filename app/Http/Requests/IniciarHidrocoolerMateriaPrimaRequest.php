<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'inicio_at' => ['required', 'date', 'before_or_equal:now'],
            'temperatura_inicial_c' => ['required', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'temperatura_objetivo_c' => ['required', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'temperatura_agua_inicial_c' => ['nullable', 'numeric', 'between:-20,50', 'decimal:0,2'],
            'observacion_inicio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'equipo' => trim((string) $this->input('equipo')),
            'observacion_inicio' => filled($this->input('observacion_inicio'))
                ? trim((string) $this->input('observacion_inicio'))
                : null,
        ]);
    }
}
