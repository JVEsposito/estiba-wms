<?php

namespace App\Http\Requests;

use App\Enums\TipoBulto;
use App\Models\Posicion;
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
     * Mantiene compatibilidad con clientes que solo informan una posición de destino.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('camara_destino_id') || ! $this->filled('posicion_destino_id')) {
            return;
        }

        $camaraDestinoId = Posicion::query()
            ->whereKey($this->input('posicion_destino_id'))
            ->value('camara_id');

        if ($camaraDestinoId) {
            $this->merge(['camara_destino_id' => $camaraDestinoId]);
        }
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
            'camara_destino_id' => ['required', 'uuid', 'exists:camaras,id'],
            'posicion_destino_id' => ['nullable', 'uuid', 'exists:posiciones,id'],
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
                'sometimes',
                'array:item_material_id,cantidad,lote,proveedor,observacion',
            ],
            'datos_material.item_material_id' => [
                'required_with:datos_material',
                'uuid',
                'exists:items_materiales,id',
            ],
            'datos_material.cantidad' => [
                'required_with:datos_material',
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
