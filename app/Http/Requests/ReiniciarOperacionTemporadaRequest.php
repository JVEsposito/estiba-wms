<?php

namespace App\Http\Requests;

use App\Models\Temporada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReiniciarOperacionTemporadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reiniciar-datos-operacionales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $temporada = $this->route('temporada');
        $frase = $temporada instanceof Temporada
            ? sprintf('REINICIAR %s', $temporada->codigo)
            : null;

        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            'password' => ['required', 'string', 'max:255'],
            'confirmacion' => ['required', 'string', Rule::in(array_filter([$frase]))],
            'confirmar_exclusion_bodega' => ['accepted'],
            'confirmar_preservar_configuracion' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmacion.in' => 'La frase de confirmación no coincide con la temporada activa.',
            'confirmar_exclusion_bodega.accepted' => 'Debes confirmar que Bodega queda excluida.',
            'confirmar_preservar_configuracion.accepted' => 'Debes confirmar que temporada y catálogos se conservan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo')),
            'confirmacion' => trim((string) $this->input('confirmacion')),
        ]);
    }
}
