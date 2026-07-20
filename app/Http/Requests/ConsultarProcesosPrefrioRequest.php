<?php

namespace App\Http\Requests;

use App\Enums\EstadoProcesoPrefrio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarProcesosPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tunel_prefrio_id' => ['nullable', 'uuid', 'exists:tuneles_prefrio,id'],
            'estado' => ['nullable', Rule::enum(EstadoProcesoPrefrio::class)],
            'solo_activos' => ['nullable', 'boolean'],
            'folio' => ['nullable', 'string', 'max:50'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];
    }
}
