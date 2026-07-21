<?php

namespace App\Http\Requests;

use App\Enums\TipoEnvaseRomana;
use App\Enums\TipoServicioRomana;
use App\Rules\RutChileno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearRecepcionRomanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operar-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'cliente_id' => ['required', 'uuid', Rule::exists('clientes', 'id')->where('activo', true)],
            'tipo_servicio' => ['required', Rule::enum(TipoServicioRomana::class)],
            'cantidad_envases_declarados' => ['required', 'integer', 'min:1', 'max:100000'],
            'tipo_envase_declarado' => ['required', Rule::enum(TipoEnvaseRomana::class)],
            'numero_guia_despacho' => ['required', 'string', 'max:80'],
            'patente_camion' => ['required', 'regex:/^[A-Z0-9]{5,8}$/'],
            'patente_carro' => ['nullable', 'regex:/^[A-Z0-9]{5,8}$/'],
            'rut_conductor' => ['required', new RutChileno],
            'nombre_conductor' => ['required', 'string', 'max:150'],
            'peso_bruto' => ['required', 'numeric', 'min:1', 'max:200000', 'decimal:0,2'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente del servicio.',
            'cliente_id.exists' => 'El cliente seleccionado no está activo.',
            'tipo_servicio.required' => 'Selecciona el servicio contratado.',
            'cantidad_envases_declarados.min' => 'La guía debe declarar al menos un envase.',
            'tipo_envase_declarado.required' => 'Selecciona el tipo de envase declarado.',
            'numero_guia_despacho.required' => 'Ingresa el número de guía de despacho.',
            'patente_camion.required' => 'Ingresa la patente del camión.',
            'patente_camion.regex' => 'Ingresa una patente de camión válida, sin puntos ni guiones.',
            'patente_carro.regex' => 'Ingresa una patente de carro válida, sin puntos ni guiones.',
            'nombre_conductor.required' => 'Ingresa el nombre del conductor.',
            'peso_bruto.required' => 'Ingresa el peso bruto capturado por la romana.',
            'peso_bruto.max' => 'El peso bruto supera el máximo operacional de 200.000 kg.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_guia_despacho' => mb_strtoupper(trim((string) $this->input('numero_guia_despacho'))),
            'patente_camion' => $this->normalizarPatente($this->input('patente_camion')),
            'patente_carro' => $this->normalizarPatente($this->input('patente_carro')),
            'rut_conductor' => $this->normalizarRut($this->input('rut_conductor')),
            'nombre_conductor' => trim((string) $this->input('nombre_conductor')),
            'observacion' => filled($this->input('observacion')) ? trim((string) $this->input('observacion')) : null,
        ]);
    }

    private function normalizarPatente(mixed $valor): ?string
    {
        $patente = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $valor) ?? '');

        return $patente !== '' ? $patente : null;
    }

    private function normalizarRut(mixed $valor): string
    {
        $rut = strtoupper(preg_replace('/[^0-9K]/i', '', (string) $valor) ?? '');

        return strlen($rut) > 1 ? substr($rut, 0, -1).'-'.substr($rut, -1) : $rut;
    }
}
