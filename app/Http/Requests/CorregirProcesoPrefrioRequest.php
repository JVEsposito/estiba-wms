<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorregirProcesoPrefrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('corregir-procesos-prefrio') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'version_conocida' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'max:500'],
            'proceso' => ['required', 'array'],
            'proceso.setpoint' => ['required', 'numeric', 'between:-20,20'],
            'proceso.duracion_objetivo_minutos' => ['nullable', 'integer', 'between:1,4320'],
            'proceso.formato_referencia' => ['nullable', 'string', 'max:100'],
            'proceso.observacion' => ['nullable', 'string', 'max:2000'],
            'eventos' => ['present', 'array', 'max:250'],
            'eventos.*.id' => ['required', 'uuid', 'distinct', 'exists:eventos_prefrio,id'],
            'eventos.*.ocurrido_at' => ['required', 'date'],
            'eventos.*.observacion' => ['nullable', 'string', 'max:2000'],
            'folios' => ['present', 'array', 'max:500'],
            'folios.*.id' => ['required', 'uuid', 'distinct', 'exists:procesos_prefrio_folios,id'],
            'folios.*.incluido' => ['required', 'boolean'],
            'folios.*.posicion_tunel_prefrio_id' => ['nullable', 'uuid', 'exists:posiciones_tunel_prefrio,id'],
            'folios.*.cargado_at' => ['nullable', 'date'],
            'folios.*.temperatura_inicial' => ['nullable', 'numeric', 'between:-20,50'],
            'folios.*.temperatura_final' => ['nullable', 'numeric', 'between:-20,50'],
            'folios.*.observacion' => ['nullable', 'string', 'max:2000'],
            'nuevo_folio' => ['nullable', 'array'],
            'nuevo_folio.numero_folio' => ['required_with:nuevo_folio', 'string', 'max:100'],
            'nuevo_folio.posicion_tunel_prefrio_id' => ['required_with:nuevo_folio', 'uuid', 'exists:posiciones_tunel_prefrio,id'],
            'nuevo_folio.cargado_at' => ['required_with:nuevo_folio', 'date'],
            'nuevo_folio.temperatura_inicial' => ['nullable', 'numeric', 'between:-20,50'],
            'nuevo_folio.temperatura_final' => ['nullable', 'numeric', 'between:-20,50'],
            'nuevo_folio.observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
