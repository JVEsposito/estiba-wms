<?php

namespace App\Services\Autorizacion;

use App\Enums\RolUsuario;
use App\Models\User;

class AlcanceOperacionalUsuarioMateriales extends AlcanceOperacionalUsuario
{
    public function puedeGestionarRecepcionesMateriales(User $usuario): bool
    {
        return $usuario->activo && in_array($usuario->rol, [
            RolUsuario::Administrador,
            RolUsuario::SupervisorMateriales,
            RolUsuario::CamareroMateriales,
        ], true);
    }
}
