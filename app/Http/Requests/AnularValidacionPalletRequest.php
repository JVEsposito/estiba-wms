<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnularValidacionPalletRequest extends FormRequest
{
    public const CATEGORIAS = [
        'folio_incorrecto',
        'cantidad_cajas_incorrecta',
        'articulo_incorrecto',
        'cliente_origen_incorrecto',
        'pallet_duplicado',
        'error_etiqueta',
        'otro',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo_categoria' => ['required', Rule::in(self::CATEGORIAS)],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
