<?php

namespace App\Http\Requests;

use App\Enums\EstadoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarValidacionesPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-validaciones-pallet') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'temporada_id' => ['nullable', 'uuid', 'exists:temporadas,id'],
            'folio' => ['nullable', 'string', 'max:50'],
            'resultado' => ['nullable', Rule::enum(ResultadoValidacionPallet::class)],
            'estado' => ['nullable', Rule::enum(EstadoValidacionPallet::class)],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('folio')) {
            $this->merge([
                'folio' => mb_strtoupper(trim((string) $this->input('folio'))),
            ]);
        }
    }
}
