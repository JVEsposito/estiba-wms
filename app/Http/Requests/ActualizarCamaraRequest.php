<?php

namespace App\Http\Requests;

use App\Enums\EstadoCamara;
use Illuminate\Validation\Rule;

class ActualizarCamaraRequest extends CrearCamaraRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-camaras') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'estado' => ['sometimes', Rule::enum(EstadoCamara::class)],
        ];
    }
}
