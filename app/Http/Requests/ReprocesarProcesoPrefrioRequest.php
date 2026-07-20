<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReprocesarProcesoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supervisar-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:3', 'max:100'],
            'resultados' => ['sometimes', 'array', 'max:100'],
            'resultados.*' => ['required', 'array:folio_id,temperatura_final,observacion'],
            'resultados.*.folio_id' => ['required', 'uuid', 'exists:folios,id'],
            'resultados.*.temperatura_final' => ['nullable', 'numeric', 'between:-20,50'],
            'resultados.*.observacion' => ['nullable', 'string', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'ocurrido_at' => ['required', 'date'],
        ];
    }
}
