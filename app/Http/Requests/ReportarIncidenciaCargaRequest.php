<?php

namespace App\Http\Requests;

use App\Enums\TipoIncidenciaCarga;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportarIncidenciaCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && app(AlcanceOperacionalUsuario::class)
                ->puedeReportarIncidenciasCarga($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'tipo' => ['required', Rule::enum(TipoIncidenciaCarga::class)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'sesion_estiba_id' => ['required', 'uuid', 'exists:sesiones_estiba,id'],
        ];
    }
}
