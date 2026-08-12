<?php

namespace App\Http\Requests;

use App\Enums\ModalidadEmbarque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEmbarqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-cargas') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                'uuid',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
            'fecha_programada' => ['required', 'date'],
            'hora_programada' => ['required', 'date_format:H:i'],
            'modalidad' => ['required', Rule::enum(ModalidadEmbarque::class)],
            'referencia_correo' => ['nullable', 'string', 'max:200'],
            'nave_vuelo' => ['nullable', 'string', 'max:150'],
            'transportista' => ['nullable', 'string', 'max:180'],
            'puerto_embarque' => ['nullable', 'string', 'max:180'],
            'contenedor' => ['nullable', 'string', 'max:100'],
            'sello' => ['nullable', 'string', 'max:100'],
            'patente_camion' => ['nullable', 'string', 'max:30'],
            'patente_trasera' => ['nullable', 'string', 'max:30'],
            'documentos' => ['nullable', 'string', 'max:2000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'instructivos' => ['required', 'array', 'min:1', 'max:20'],
            'instructivos.*.numero_externo' => ['nullable', 'string', 'max:150'],
            'instructivos.*.recibidor' => ['nullable', 'string', 'max:180'],
            'instructivos.*.destino_pais' => ['nullable', 'string', 'max:120'],
            'instructivos.*.destino_ciudad' => ['nullable', 'string', 'max:120'],
            'instructivos.*.cantidad_pallets' => ['nullable', 'integer', 'min:0', 'max:999'],
            'instructivos.*.cantidad_cajas' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'instructivos.*.booking' => ['nullable', 'string', 'max:150'],
            'instructivos.*.sps' => ['nullable', 'string', 'max:150'],
            'instructivos.*.dus' => ['nullable', 'string', 'max:150'],
            'instructivos.*.planilla_sag' => ['nullable', 'string', 'max:150'],
            'instructivos.*.sello_sag' => ['nullable', 'string', 'max:150'],
            'instructivos.*.observacion' => ['nullable', 'string', 'max:1000'],
            'autorizar_sobrecupo' => ['sometimes', 'boolean'],
            'motivo_sobrecupo' => [
                Rule::requiredIf($this->boolean('autorizar_sobrecupo')),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $campos = [
            'referencia_correo', 'nave_vuelo', 'transportista', 'puerto_embarque',
            'contenedor', 'sello', 'patente_camion', 'patente_trasera',
            'documentos', 'observacion', 'motivo_sobrecupo',
        ];
        $normalizados = [];

        foreach ($campos as $campo) {
            $normalizados[$campo] = $this->textoOpcional($this->input($campo));
        }

        $normalizados['patente_camion'] = $normalizados['patente_camion']
            ? mb_strtoupper($normalizados['patente_camion'])
            : null;
        $normalizados['patente_trasera'] = $normalizados['patente_trasera']
            ? mb_strtoupper($normalizados['patente_trasera'])
            : null;
        $normalizados['autorizar_sobrecupo'] = $this->boolean('autorizar_sobrecupo');
        $normalizados['instructivos'] = collect($this->input('instructivos', []))
            ->map(function (mixed $fila): mixed {
                if (! is_array($fila)) {
                    return $fila;
                }

                foreach ([
                    'numero_externo', 'recibidor', 'destino_pais', 'destino_ciudad',
                    'booking', 'sps', 'dus', 'planilla_sag', 'sello_sag', 'observacion',
                ] as $campo) {
                    $fila[$campo] = $this->textoOpcional($fila[$campo] ?? null);
                }

                return $fila;
            })
            ->all();

        $this->merge($normalizados);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return $valor === null ? null : trim((string) $valor);
        }

        $texto = trim($valor);

        return $texto === '' ? null : $texto;
    }
}
