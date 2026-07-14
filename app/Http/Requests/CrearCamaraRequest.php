<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CrearCamaraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configurar-camaras') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'tipo' => [
                'required',
                Rule::in(['transito', 'almacenaje', 'preparacion', 'despacho']),
            ],
            'bandas' => ['required', 'integer', 'min:1', 'max:40'],
            'posiciones_por_banda' => ['required', 'integer', 'min:1', 'max:40'],
            'niveles' => ['required', 'integer', 'min:1', 'max:10'],
            'posiciones_fuera_servicio' => ['sometimes', 'array', 'max:1000'],
            'posiciones_fuera_servicio.*' => [
                'required',
                'array:banda,posicion,nivel',
            ],
            'posiciones_fuera_servicio.*.banda' => ['required', 'integer', 'min:1'],
            'posiciones_fuera_servicio.*.posicion' => ['required', 'integer', 'min:1'],
            'posiciones_fuera_servicio.*.nivel' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $bandas = (int) $this->input('bandas');
            $posiciones = (int) $this->input('posiciones_por_banda');
            $niveles = (int) $this->input('niveles');

            if ($bandas * $posiciones * $niveles > 1000) {
                $validator->errors()->add(
                    'bandas',
                    'Una cámara no puede superar 1.000 posiciones configuradas.',
                );
            }

            $coordenadas = [];

            foreach ($this->input('posiciones_fuera_servicio', []) as $indice => $item) {
                $banda = (int) $item['banda'];
                $posicion = (int) $item['posicion'];
                $nivel = (int) $item['nivel'];
                $clave = "{$banda}:{$posicion}:{$nivel}";

                if ($banda > $bandas || $posicion > $posiciones || $nivel > $niveles) {
                    $validator->errors()->add(
                        "posiciones_fuera_servicio.{$indice}",
                        'La posición fuera de servicio no pertenece al plano indicado.',
                    );
                }

                if (isset($coordenadas[$clave])) {
                    $validator->errors()->add(
                        "posiciones_fuera_servicio.{$indice}",
                        'La posición fuera de servicio está repetida.',
                    );
                }

                $coordenadas[$clave] = true;
            }
        }];
    }
}
