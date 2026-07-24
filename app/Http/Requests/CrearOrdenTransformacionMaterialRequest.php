<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearOrdenTransformacionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-transformaciones-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_receta_material_id' => [
                'required',
                'uuid',
                'exists:versiones_recetas_materiales,id',
            ],
            'cantidad_planificada_salida' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'linea' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:80'],
            'fecha_operacional' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
