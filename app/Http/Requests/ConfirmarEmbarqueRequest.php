<?php

namespace App\Http\Requests;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\PrioridadCarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarEmbarqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-cargas') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version_esperada' => ['required', 'integer', 'min:1'],
            'prioridad' => ['sometimes', Rule::enum(PrioridadCarga::class)],
            'camara_objetivo_id' => [
                'nullable', 'uuid',
                Rule::exists('camaras', 'id')
                    ->where('estado', EstadoCamara::Activa->value)
                    ->where('contenido', ContenidoCamara::Productos->value),
            ],
            'anden_previsto_id' => [
                'nullable', 'uuid',
                Rule::exists('andenes', 'id')->where('activo', true),
            ],
        ];
    }
}
