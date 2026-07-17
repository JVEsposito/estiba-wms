<?php

namespace App\Http\Requests;

use App\Enums\ContenidoCamara;
use App\Enums\EstadoCamara;
use App\Enums\PrioridadCarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-cargas') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'numero_orden_externa' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('cargas', 'numero_orden_externa'),
            ],
            'prioridad' => ['sometimes', Rule::enum(PrioridadCarga::class)],
            'camara_objetivo_id' => [
                'nullable',
                'uuid',
                Rule::exists('camaras', 'id')->where(
                    'estado',
                    EstadoCamara::Activa->value,
                )->where('contenido', ContenidoCamara::Productos->value),
            ],
            'anden_previsto_id' => [
                'nullable',
                'uuid',
                Rule::exists('andenes', 'id')->where('activo', true),
            ],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_orden_externa' => $this->textoNormalizado(
                $this->input('numero_orden_externa'),
            ),
            'observacion' => $this->textoNormalizado($this->input('observacion')),
        ]);
    }

    private function textoNormalizado(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }
}
