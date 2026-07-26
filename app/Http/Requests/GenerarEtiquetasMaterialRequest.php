<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerarEtiquetasMaterialRequest extends FormRequest
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
            'perfil_id' => [
                'required',
                'uuid',
                Rule::exists('perfiles_impresion_etiquetas', 'id')->where('activo', true),
            ],
            'formato' => ['required', Rule::in(['pdf', 'zpl'])],
            'canal' => ['required', Rule::in(['oficina_descarga', 'pda_directa'])],
            'folio_ids' => ['required', 'array', 'min:1', 'max:500'],
            'folio_ids.*' => ['required', 'uuid', 'distinct', 'exists:folios,id'],
            'copias' => ['required', 'integer', 'between:1,20'],
            'motivo_reimpresion' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('canal') === 'pda_directa'
                && $this->input('formato') !== 'zpl') {
                $validator->errors()->add(
                    'formato',
                    'La impresión directa desde PDA requiere formato ZPL.',
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'formato' => mb_strtolower(trim((string) $this->input('formato'))),
            'canal' => mb_strtolower(trim((string) $this->input('canal', 'oficina_descarga'))),
            'copias' => $this->input('copias', 1),
            'motivo_reimpresion' => $this->filled('motivo_reimpresion')
                ? trim((string) $this->input('motivo_reimpresion'))
                : null,
        ]);
    }
}
