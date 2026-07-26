<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistrarResultadoImpresionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('imprimir-etiquetas-materiales') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'estado' => ['required', Rule::in(['enviado', 'fallido', 'indeterminado'])],
            'bytes_enviados' => ['required', 'integer', 'between:0,5000000'],
            'error' => ['nullable', 'string', 'max:2000'],
            'impresora' => ['required', 'array'],
            'impresora.nombre' => ['required', 'string', 'max:100'],
            'impresora.host' => ['required', 'ipv4'],
            'impresora.puerto' => ['required', 'integer', 'between:1,65535'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('estado') === 'fallido'
                && (int) $this->input('bytes_enviados', 0) !== 0) {
                $validator->errors()->add(
                    'bytes_enviados',
                    'Un envío parcialmente escrito debe informarse como indeterminado.',
                );
            }
            if (in_array($this->input('estado'), ['fallido', 'indeterminado'], true)
                && ! $this->filled('error')) {
                $validator->errors()->add(
                    'error',
                    'Describe el error o la incertidumbre de la impresión.',
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $impresora = is_array($this->input('impresora'))
            ? $this->input('impresora')
            : [];
        $this->merge([
            'estado' => mb_strtolower(trim((string) $this->input('estado'))),
            'error' => $this->filled('error')
                ? trim((string) $this->input('error'))
                : null,
            'impresora' => [
                'nombre' => trim((string) ($impresora['nombre'] ?? '')),
                'host' => trim((string) ($impresora['host'] ?? '')),
                'puerto' => $impresora['puerto'] ?? null,
            ],
        ]);
    }
}
