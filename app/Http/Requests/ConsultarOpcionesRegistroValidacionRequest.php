<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarOpcionesRegistroValidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-validaciones-pallet') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'temporada_id' => ['nullable', 'uuid', 'exists:temporadas,id'],
        ];
    }
}
