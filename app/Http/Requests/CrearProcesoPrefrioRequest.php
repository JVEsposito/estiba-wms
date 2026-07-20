<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearProcesoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tunel_prefrio_id' => ['required', 'uuid', 'exists:tuneles_prefrio,id'],
            'setpoint' => ['required', 'numeric', 'between:-20,20'],
            'duracion_objetivo_minutos' => ['nullable', 'integer', 'min:1', 'max:4320'],
            'formato_referencia' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'ocurrido_at' => ['required', 'date'],
        ];
    }
}
