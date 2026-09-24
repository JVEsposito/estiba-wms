<?php

namespace App\Http\Requests;

use App\Models\Camara;
use App\Models\User;
use App\Services\Autorizacion\AlcanceOperacionalUsuario;
use Illuminate\Foundation\Http\FormRequest;

class BuscarFoliosMaterialUbicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $camaraId = $this->input('camara_id');
        $camara = is_string($camaraId) ? Camara::query()->find($camaraId) : null;

        return $usuario instanceof User
            && $camara !== null
            && app(AlcanceOperacionalUsuario::class)->puedeOperarCamara($usuario, $camara);
    }

    public function rules(): array
    {
        return [
            'prefijo' => ['required', 'string', 'min:2', 'max:50'],
            'camara_id' => ['required', 'uuid', 'exists:camaras,id'],
            'solo_camara' => ['sometimes', 'boolean'],
        ];
    }
}
