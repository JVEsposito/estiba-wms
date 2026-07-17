<?php

namespace App\Http\Requests;

use App\Enums\MotivoValidacionPallet;
use App\Enums\ResultadoValidacionPallet;
use App\Enums\TipoBulto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarValidacionPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('validar-pallets') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'numero_folio' => ['required', 'string', 'max:50'],
            'tipo_bulto' => ['required', Rule::in([TipoBulto::Pallet->value, TipoBulto::Saldo->value])],
            'cantidad_cajas' => ['required', 'integer', 'min:1'],
            'temporada_id' => ['required', 'uuid', 'exists:temporadas,id'],
            'catalogo_version' => ['required', 'integer', 'min:1'],
            'articulo_validacion_id' => ['required', 'uuid', 'exists:articulos_validacion,id'],
            'origen_validacion_id' => ['required', 'uuid', 'exists:origenes_validacion,id'],
            'resultado' => ['required', Rule::enum(ResultadoValidacionPallet::class)],
            'motivo' => ['nullable', Rule::enum(MotivoValidacionPallet::class), 'required_unless:resultado,aprobado'],
            'observacion' => ['nullable', 'string', 'max:2000', 'required_if:motivo,otro'],
            'generado_dispositivo_at' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_folio' => mb_strtoupper(trim((string) $this->input('numero_folio'))),
            'observacion' => filled($this->input('observacion'))
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }
}
