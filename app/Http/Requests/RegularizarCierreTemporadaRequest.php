<?php

namespace App\Http\Requests;

use App\Enums\CategoriaPendienteCierre;
use App\Enums\MotivoRegularizacionCierre;
use App\Services\Temporadas\Cierre\ServicioRegularizacionCierreTemporada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegularizarCierreTemporadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'categoria' => ['required', Rule::enum(CategoriaPendienteCierre::class)],
            'ids' => ['required', 'array', 'min:1', 'max:'.ServicioRegularizacionCierreTemporada::MAXIMO_POR_SOLICITUD],
            'ids.*' => ['required', 'string', 'max:64', 'distinct'],
            'motivo_categoria' => ['required', Rule::enum(MotivoRegularizacionCierre::class)],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'Selecciona al menos un registro para regularizar.',
            'motivo_categoria.required' => 'Elige la categoría del motivo.',
            'motivo.required' => 'Describe por qué se regularizan estos registros.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
