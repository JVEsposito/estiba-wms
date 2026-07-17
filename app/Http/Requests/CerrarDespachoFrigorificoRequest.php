<?php

namespace App\Http\Requests;

use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;

class CerrarDespachoFrigorificoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && app(AlcanceOperacionalUsuario::class)
                ->puedeCerrarDespachoFrigorifico($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'patente' => ['required', 'string', 'max:20'],
            'conductor' => ['required', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
