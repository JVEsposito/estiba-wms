<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarSagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-sag') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['codigo_sag', 'rut'])],
            'valor' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = (string) $this->input('tipo');
        $valor = mb_strtoupper(trim((string) $this->input('valor')));
        $valor = $tipo === 'rut'
            ? str_replace(['.', ' '], '', $valor)
            : preg_replace('/\s+/', '', $valor);

        $this->merge(['tipo' => $tipo, 'valor' => $valor]);
    }
}
