<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearRecepcionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-recepciones-materiales') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'proveedor_material_id' => ['required', 'uuid', 'exists:proveedores_materiales,id'],
            'numero_guia_despacho' => ['required', 'string', 'max:50'],
            'fecha_documento' => ['nullable', 'date'],
            'orden_compra' => ['nullable', 'string', 'max:80'],
            'patente' => ['nullable', 'string', 'max:20'],
            'transportista' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.item_material_id' => ['required', 'uuid', 'exists:items_materiales,id'],
            'detalles.*.cantidad_documental' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'detalles.*.cantidad_recibida' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'detalles.*.cantidad_rechazada' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:2000'],
            'detalles.*.bultos' => ['required', 'array', 'min:1', 'max:500'],
            'detalles.*.bultos.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'detalles.*.bultos.*.lote_proveedor' => ['nullable', 'string', 'max:100'],
            'detalles.*.bultos.*.fecha_fabricacion' => ['nullable', 'date'],
            'detalles.*.bultos.*.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.bultos.*.bloqueado' => ['nullable', 'boolean'],
            'detalles.*.bultos.*.motivo_bloqueo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_guia_despacho' => trim((string) $this->input('numero_guia_despacho')),
            'orden_compra' => $this->limpiar($this->input('orden_compra')),
            'patente' => $this->limpiar($this->input('patente')),
            'transportista' => $this->limpiar($this->input('transportista')),
            'observacion' => $this->limpiar($this->input('observacion')),
        ]);
    }

    private function limpiar(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
