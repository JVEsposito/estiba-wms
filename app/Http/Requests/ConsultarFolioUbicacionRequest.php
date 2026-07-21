<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarFolioUbicacionRequest extends FormRequest
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
            'numero_folio' => ['required', 'string', 'max:50'],
        ];
    }
}
