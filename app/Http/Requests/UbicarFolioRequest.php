<?php

namespace App\Http\Requests;

use App\Enums\TipoBulto;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbicarFolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof User
            && app(AlcanceOperacionalUsuario::class)->puedeOperarAlgunaCamara($usuario);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'numero_folio' => ['required', 'string', 'max:50'],
            'tipo_bulto' => ['required', Rule::enum(TipoBulto::class)],
            'posicion_destino_id' => ['required', 'uuid', 'exists:posiciones,id'],
            'sesion_destino_id' => ['required', 'uuid', 'exists:sesiones_estiba,id'],
            'version_destino_conocida' => ['required', 'integer', 'min:0'],
            'generado_dispositivo_at' => ['required', 'date'],
            'advertencias_confirmadas' => ['sometimes', 'array', 'max:5'],
            'advertencias_confirmadas.*' => ['required', 'string', 'max:100', 'distinct'],
            'datos_folio' => [
                'sometimes',
                'array:condicion_sag_id,fecha_ingreso,variedad,calibre,marca,exportadora',
            ],
            'datos_folio.condicion_sag_id' => [
                'nullable',
                'uuid',
                'exists:condiciones_sag,id',
            ],
            'datos_folio.fecha_ingreso' => ['nullable', 'date'],
            'datos_folio.variedad' => ['nullable', 'string', 'max:100'],
            'datos_folio.calibre' => ['nullable', 'string', 'max:100'],
            'datos_folio.marca' => ['nullable', 'string', 'max:150'],
            'datos_folio.exportadora' => ['nullable', 'string', 'max:150'],
            'datos_material' => [
                Rule::requiredIf($this->input('tipo_bulto') === TipoBulto::Material->value),
                'array:item_material_id,cantidad,lote,proveedor,observacion',
            ],
            'datos_material.item_material_id' => [
                Rule::requiredIf($this->input('tipo_bulto') === TipoBulto::Material->value),
                'uuid',
                'exists:items_materiales,id',
            ],
            'datos_material.cantidad' => [
                Rule::requiredIf($this->input('tipo_bulto') === TipoBulto::Material->value),
                'numeric',
                'gt:0',
                'decimal:0,3',
            ],
            'datos_material.lote' => ['nullable', 'string', 'max:100'],
            'datos_material.proveedor' => ['nullable', 'string', 'max:180'],
            'datos_material.observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
