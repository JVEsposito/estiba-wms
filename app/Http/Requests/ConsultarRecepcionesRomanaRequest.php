<?php

namespace App\Http\Requests;

use App\Enums\EstadoRecepcionRomana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarRecepcionesRomanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('consultar-romana') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'temporada_id' => ['nullable', 'uuid', 'exists:temporadas,id'],
            'estado' => ['nullable', Rule::enum(EstadoRecepcionRomana::class)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
