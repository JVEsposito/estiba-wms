<?php

namespace App\Http\Requests;

use App\Enums\PrioridadCarga;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarDespachoDirectoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario?->can('gestionar-cargas') === true
            && app(AlcanceOperacionalUsuario::class)
                ->puedeCerrarDespachoFrigorifico($usuario);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'folios' => ['required', 'array', 'min:1', 'max:26'],
            'folios.*' => ['required', 'string', 'max:100', 'distinct:strict'],
            'numero_orden_externa' => ['nullable', 'string', 'max:100'],
            'prioridad' => ['sometimes', Rule::enum(PrioridadCarga::class)],
            'anden_id' => [
                'required',
                'uuid',
                Rule::exists('andenes', 'id')->where(
                    fn ($consulta) => $consulta->where('activo', true),
                ),
            ],
            'patente' => ['required', 'string', 'max:20'],
            'conductor' => ['required', 'string', 'max:150'],
            'ocurrido_at' => ['required', 'date', 'before_or_equal:now'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
