<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularRepaletizajeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
