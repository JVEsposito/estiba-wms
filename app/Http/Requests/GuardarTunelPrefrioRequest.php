<?php

namespace App\Http\Requests;

use App\Enums\EstadoAdministrativoTunelPrefrio;
use App\Enums\EstadoTecnicoTunelPrefrio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarTunelPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-tuneles-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'capacidad_posiciones' => ['required', 'integer', 'min:2', 'max:100', 'multiple_of:2'],
            'setpoint_habitual' => ['nullable', 'numeric', 'between:-20,20'],
            'estado_administrativo' => [
                Rule::requiredIf($this->isMethod('PUT')),
                Rule::enum(EstadoAdministrativoTunelPrefrio::class),
            ],
            'estado_tecnico' => [
                'sometimes',
                Rule::enum(EstadoTecnicoTunelPrefrio::class),
            ],
            'codigo_externo' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
