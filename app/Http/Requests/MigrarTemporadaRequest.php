<?php

namespace App\Http\Requests;

use App\Models\Temporada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MigrarTemporadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-accesos') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $destino = $this->route('temporada');

        return [
            'temporada_origen_id' => [
                'required',
                'uuid',
                Rule::exists('temporadas', 'id'),
                Rule::notIn([$destino instanceof Temporada ? $destino->id : null]),
            ],
            'copiar_catalogo_validacion' => ['required', 'boolean'],
            'copiar_catalogo_materiales' => ['required', 'boolean'],
            'migrar_inventario_materiales' => ['required', 'boolean'],
            'activar_destino' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'copiar_catalogo_validacion',
            'copiar_catalogo_materiales',
            'migrar_inventario_materiales',
            'activar_destino',
        ] as $campo) {
            if ($this->has($campo)) {
                $this->merge([$campo => $this->boolean($campo)]);
            }
        }
    }
}
